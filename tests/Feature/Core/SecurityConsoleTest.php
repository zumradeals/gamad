<?php

use App\Core\Identity\SecurityAccessEvent;
use App\Core\Identity\SecurityRetention;
use App\Enums\PlatformRole;
use App\Livewire\TraitementsCore;
use App\Models\AdminAction;
use App\Models\User;
use App\Services\CoreConsoleObservations;
use App\Services\SecurityObservations;
use App\Support\JournalReader;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;

function accessTrace(array $data = []): SecurityAccessEvent
{
    return SecurityAccessEvent::query()->create($data + [
        'occurred_at' => now(), 'outcome' => 'failure', 'source_key' => hash('sha256', 'source'),
        'target_key' => hash('sha256', 'target'), 'ip' => '198.51.100.25', 'user_agent' => 'Security Test Browser',
    ]);
}

it('observe les vrais événements Fortify sans secret ni double journalisation, y compris le limiteur nommé', function () {
    $user = User::factory()->create(['password' => Hash::make('motdepasse1')]);
    config(['gamad.limits.login_per_minute' => 2]);
    for ($i = 0; $i < 3; $i++) {
        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.25'])->withHeader('User-Agent', 'Security Test Browser')
            ->post('/login', ['email' => strtoupper($user->email), 'password' => 'NEVER-STORE-THIS', 'secret_extra' => 'NEVER-EXTRA']);
    }
    expect(SecurityAccessEvent::count())->toBe(3);
    expect(SecurityAccessEvent::where('outcome', 'failure')->count())->toBe(2);
    expect(SecurityAccessEvent::where('outcome', 'limited')->count())->toBe(1);
    $trace = SecurityAccessEvent::first();
    expect($trace->target_id)->toBe($user->id)->and($trace->authenticated_id)->toBeNull()->and($trace->ip)->toBe('198.51.100.25');
    $stored = json_encode(DB::table('security_access_events')->get());
    foreach (['198.51.100.25', 'Security Test Browser', $user->email, 'NEVER-STORE-THIS', 'NEVER-EXTRA'] as $secret) {
        expect($stored)->not->toContain($secret);
    }
    expect((int) DB::table('security_access_hours')->sum('failure'))->toBe(2);
    expect((int) DB::table('security_access_hours')->sum('limited'))->toBe(1);
});

it('distingue une connexion authentifiée d’un compte visé et corrèle les adresses inconnues sans les conserver', function () {
    $user = User::factory()->create(['password' => Hash::make('motdepasse1')]);
    $this->post('/login', ['email' => $user->email, 'password' => 'motdepasse1'])->assertRedirect();
    $success = SecurityAccessEvent::where('outcome', 'success')->firstOrFail();
    expect($success->authenticated_id)->toBe($user->id)->and($success->target_id)->toBe($user->id);
    $this->post('/logout');
    $this->post('/login', ['email' => 'missing@example.test', 'password' => 'wrong']);
    $this->post('/login', ['email' => 'MISSING@example.test', 'password' => 'wrong']);
    $unknown = SecurityAccessEvent::where('outcome', 'failure')->get();
    expect($unknown)->toHaveCount(2)->and($unknown[0]->target_key)->toBe($unknown[1]->target_key)->and($unknown[0]->target_id)->toBeNull();
    expect(json_encode(DB::table('security_access_events')->get()))->not->toContain('missing@example.test');
});

it('repère force brute, sources distribuées, identifiants multiples et comptes sensibles sans accusation', function () {
    $sensitive = User::factory()->superAdmin()->create();
    for ($i = 0; $i < 12; $i++) {
        accessTrace(['source_key' => hash('sha256', 'source-'.$i), 'target_id' => $sensitive->id, 'sensitive_target' => true]);
        accessTrace(['source_key' => hash('sha256', 'mass-source'), 'target_key' => hash('sha256', 'target-'.$i)]);
    }
    $incidents = app(SecurityObservations::class)->incidents(1, true);
    $titles = implode(' ', array_column($incidents, 'title'));
    expect($titles)->toContain('Force brute')->toContain('distribuées')->toContain('sensible')->toContain('Identifiants multiples');
    expect(implode(' ', array_column($incidents, 'certainty')))->toContain('auteur physique non identifié');
    expect(app(CoreConsoleObservations::class)->snapshot(1)['health']['Identity']['state'])->toBe('dégradé');
});

it('repère une source massive, les limitations et une réussite après les échecs qui la précèdent', function () {
    for ($i = 0; $i < 35; $i++) {
        accessTrace(['occurred_at' => now()->subMinutes(2), 'outcome' => $i < 21 ? 'limited' : 'failure']);
    }
    accessTrace(['outcome' => 'success', 'authenticated_id' => (string) Str::uuid()]);
    $titles = implode(' ', array_column(app(SecurityObservations::class)->incidents(1, true), 'title'));
    expect($titles)->toContain('Tentatives massives')->toContain('Limitations excessives')->toContain('réussie après');
    SecurityAccessEvent::where('outcome', 'success')->update(['occurred_at' => now()->subMinutes(3)]);
    expect(implode(' ', array_column(app(SecurityObservations::class)->incidents(1, true), 'title')))->not->toContain('réussie après');
});

it('ne rapproche jamais les origines absentes entre elles', function () {
    for ($i = 0; $i < 35; $i++) {
        accessTrace(['source_key' => null, 'ip' => null, 'target_key' => hash('sha256', 'target-'.$i)]);
    }
    expect(app(SecurityObservations::class)->incidents(1, true))->toBe([]);
    $investigation = app(SecurityObservations::class)->investigate(User::factory()->superAdmin()->create(), SecurityAccessEvent::first()->id, 'source');
    expect($investigation['events'])->toHaveCount(1);
});

it('protège les détails et journalise chaque divulgation sans copier les IP dans le journal', function () {
    $trace = accessTrace(['target_id' => User::factory()->create()->id]);
    $admin = User::factory()->superAdmin()->create();
    $page = Livewire::actingAs($admin)->test(TraitementsCore::class)->assertDontSee('198.51.100.25');
    $page->call('investigate', $trace->id)->assertSee('198.51.100.25')->assertSee('Security Test Browser')->assertSee('auteur non identifié');
    $page->call('$refresh');
    expect(AdminAction::where('action', 'console.security.investigate')->count())->toBe(2);
    expect(JournalReader::query(User::factory()->moderator()->create(), 'console')->count())->toBe(0);
    expect(json_encode(AdminAction::all()))->not->toContain('198.51.100.25')->not->toContain('Security Test Browser');
    expect(json_encode($page->get('investigationId')))->not->toContain('198.51.100.25');
    foreach ([User::factory()->create(), User::factory()->moderator()->create()] as $outsider) {
        try {
            app(SecurityObservations::class)->investigate($outsider, $trace->id, 'target');
            test()->fail('Access must be denied');
        } catch (AuthorizationException) {
            expect(true)->toBeTrue();
        }
    }
    // An already open component loses access as soon as the actor loses the role.
    $admin->forceFill(['platform_role' => PlatformRole::Member])->save();
    auth()->setUser($admin->fresh());
    $page->call('investigate', $trace->id)->assertNotFound();
});

it('borne l’investigation et exclut les traces expirées avant même le nettoyage', function () {
    $admin = User::factory()->superAdmin()->create();
    $old = accessTrace(['occurred_at' => now()->subDays(31)]);
    for ($i = 0; $i < 110; $i++) {
        accessTrace();
    }
    $detail = app(SecurityObservations::class)->investigate($admin, SecurityAccessEvent::latest('occurred_at')->first()->id, 'target');
    expect($detail['events'])->toHaveCount(100)->and($detail['truncated'])->toBeTrue();
    expect(fn () => app(SecurityObservations::class)->investigate($admin, $old->id, 'target'))->toThrow(ModelNotFoundException::class);
    DB::table('security_access_hours')->insert([['hour' => now()->subDays(91)->startOfHour(), 'failure' => 1], ['hour' => now()->startOfHour(), 'failure' => 110]]);
    $result = app(SecurityRetention::class)->purge(10);
    expect($result)->toBe(['details' => 1, 'hours' => 1]);
    expect(SecurityAccessEvent::count())->toBe(110)->and(DB::table('security_access_hours')->count())->toBe(1);
});
