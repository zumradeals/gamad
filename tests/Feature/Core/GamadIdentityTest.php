<?php

use App\Core\Identity\IdentityEvent;
use App\Core\Identity\IdentityObservations;
use App\Core\Identity\Sessions;
use App\Livewire\TraitementsCore;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('attribue un numéro personnel distinct sans changer les identifiants existants ni accorder de statut', function () {
    $user = User::factory()->create();
    $number = $user->gamad_number;
    $uuid = $user->id;
    $profileId = $user->profile->public_id;
    expect($number)->toMatch('/^GMD-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$/')->not->toBe($uuid)->not->toBe($profileId);
    expect($user->fresh()->gamad_number)->toBe($number);
    $user->update(['name' => 'Nouveau nom affiché']);
    expect($user->fresh()->gamad_number)->toBe($number)->and($user->id)->toBe($uuid)->and($user->profile->public_id)->toBe($profileId);
    expect($user->toArray())->not->toHaveKey('gamad_number');
    $this->actingAs($user)->get(route('espace'))->assertOk()->assertSee($number)->assertSee('ni une carte ou un statut de membre GAMAD');
    $other = User::factory()->create();
    $this->actingAs($other)->get(route('espace'))->assertDontSee($number);
    $this->get(route('personnes.show', $user->profile->public_id))->assertDontSee($number);
});

it('refuse la modification du numéro par le modèle comme par SQL et empêche son choix à la création', function () {
    $user = User::factory()->create();
    expect(fn () => $user->forceFill(['gamad_number' => 'GMD-0000-0000-0000'])->save())->toThrow(LogicException::class);
    expect(fn () => DB::transaction(fn () => DB::table('users')->where('id', $user->id)->update(['gamad_number' => 'GMD-0000-0000-0000'])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('users')->where('id', $user->id)->update(['gamad_number' => null])))->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => User::factory()->make()->forceFill(['gamad_number' => $user->fresh()->gamad_number])->save()))->toThrow(QueryException::class);
});

it('conserve uniquement la réservation du numéro fermé et ne la réattribue jamais', function () {
    $user = User::factory()->create();
    $number = $user->gamad_number;
    $id = $user->id;
    fermerCompte($user);
    expect(User::find($id))->toBeNull();
    expect(DB::table('gamad_number_reservations')->where('number', $number)->exists())->toBeTrue();
    expect(array_keys((array) DB::table('gamad_number_reservations')->where('number', $number)->first()))->toBe(['number']);
    expect(User::factory()->create()->gamad_number)->not->toBe($number);
    foreach ([fn () => DB::table('gamad_number_reservations')->where('number', $number)->delete(),
        fn () => DB::table('gamad_number_reservations')->where('number', $number)->update(['number' => 'GMD-0000-0000-0000']),
        fn () => DB::statement('truncate gamad_number_reservations cascade')] as $change) {
        expect(fn () => DB::transaction($change))->toThrow(QueryException::class);
    }
    $migration = require database_path('migrations/2026_12_14_100000_complete_identity_and_assign_gamad_numbers.php');
    expect(fn () => $migration->down())->toThrow(LogicException::class);
});

it('réessaie réellement après une collision avec un numéro réservé, y compris après fermeture', function () {
    $user = User::factory()->create();
    $number = $user->gamad_number;
    fermerCompte($user);
    $original = DB::selectOne("select pg_get_functiondef('gamad_number_candidate()'::regprocedure) as definition")->definition;
    DB::statement('create temporary sequence candidate_probe');
    try {
        DB::unprepared("create or replace function gamad_number_candidate() returns text as \$\$ select case when nextval('candidate_probe') = 1 then '$number' else 'GMD-ABCD-EFAB-CDEF' end \$\$ language sql volatile");
        $new = User::factory()->create();
        expect($new->gamad_number)->toBe('GMD-ABCD-EFAB-CDEF');
        expect((int) DB::selectOne('select last_value from candidate_probe')->last_value)->toBe(2);
    } finally {
        DB::unprepared($original);
    }
});

it('annule la création plutôt que de perdre la garantie si les collisions persistent', function () {
    $number = User::factory()->create()->gamad_number;
    $before = User::count();
    $original = DB::selectOne("select pg_get_functiondef('gamad_number_candidate()'::regprocedure) as definition")->definition;
    try {
        DB::unprepared("create or replace function gamad_number_candidate() returns text as \$\$ select '$number'::text \$\$ language sql volatile");
        expect(fn () => DB::transaction(fn () => User::factory()->create()))->toThrow(QueryException::class);
        expect(User::count())->toBe($before);
    } finally {
        DB::unprepared($original);
    }
});

it('ne permet pas de se connecter en utilisant le numéro GAMAD comme identifiant ou secret', function () {
    $user = User::factory()->create();
    $this->post('/login', ['email' => $user->gamad_number, 'password' => 'motdepasse1'])->assertSessionHasErrors();
    $this->assertGuest();
    $this->post('/login', ['email' => $user->email, 'password' => $user->gamad_number])->assertSessionHasErrors();
    $this->assertGuest();
    $this->get(route('espace'))->assertRedirect();
});

it('compte les catégories exclusives et les sessions réellement non expirées sans charger leur contenu', function () {
    $active = User::factory()->create();
    User::factory()->unverified()->create();
    $suspended = User::factory()->create(['suspended_at' => now(), 'suspension_reason' => 'Suspension de recette']);
    User::factory()->unverified()->create(['suspended_at' => now(), 'suspension_reason' => 'Suspension de recette']);
    config(['session.driver' => 'database', 'session.lifetime' => 120]);
    foreach ([['valid', $active->id, now()->timestamp], ['expired', $active->id, now()->subMinutes(121)->timestamp],
        ['suspended', $suspended->id, now()->timestamp], ['guest', null, now()->timestamp], ['orphan', (string) Str::uuid(), now()->timestamp]] as [$id, $userId, $time]) {
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $userId, 'last_activity' => $time, 'payload' => 'PRIVATE-PAYLOAD', 'ip_address' => '198.51.100.99', 'user_agent' => 'PRIVATE-AGENT']);
    }
    $snapshot = app(IdentityObservations::class)->snapshot(24);
    expect($snapshot['accounts'])->toMatchArray(['total' => 4, 'active' => 1, 'pending' => 1, 'suspended' => 2, 'verified' => 2, 'unverified' => 2]);
    expect($snapshot['sessions']['count'])->toBe(1);
    expect(json_encode($snapshot))->not->toContain('PRIVATE')->not->toContain('198.51.100.99')->not->toContain($active->id)->not->toContain($active->email);
    config(['session.driver' => 'file']);
    expect(app(Sessions::class)->authenticatedCount())->toBeNull();
    expect(app(IdentityObservations::class)->snapshot(24)['sessions']['state'])->toBe('non disponible');
});

it('observe les événements Identity par type et période, sans confondre événements et nombre de sessions révoquées', function () {
    $user = User::factory()->create();
    foreach ([IdentityEvent::PASSWORD_CHANGED, IdentityEvent::PASSWORD_RESET, IdentityEvent::SESSION_REVOKED, IdentityEvent::OTHER_SESSIONS_REVOKED, IdentityEvent::ACCOUNT_CLOSED] as $type) {
        IdentityEvent::record($user->id, $type, ['sessions' => 99]);
    }
    $this->travel(2)->hours();
    IdentityEvent::record($user->id, IdentityEvent::PASSWORD_CHANGED);
    $short = collect(app(IdentityObservations::class)->snapshot(1)['events'])->keyBy('type');
    $long = collect(app(IdentityObservations::class)->snapshot(24)['events'])->keyBy('type');
    expect($short[IdentityEvent::PASSWORD_CHANGED]['total'])->toBe(1)->and($short[IdentityEvent::PASSWORD_RESET]['total'])->toBe(0);
    expect($long[IdentityEvent::PASSWORD_CHANGED]['total'])->toBe(2)->and($long[IdentityEvent::OTHER_SESSIONS_REVOKED]['total'])->toBe(1);
    Livewire::actingAs(User::factory()->superAdmin()->create())->test(TraitementsCore::class)
        ->assertSee('Comptes utilisateurs et fonctionnement Identity')->assertSee('Mots de passe réinitialisés')->assertSee('identity_events')->assertDontSee('PRIVATE-PAYLOAD');
    Livewire::actingAs(User::factory()->moderator()->create())->test(TraitementsCore::class)->assertNotFound();
});
