<?php

use App\Livewire\Espace;
use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;

it('affiche le nom et l’adresse e-mail de la personne connectée seulement', function () {
    $user = User::factory()->create(['name' => 'Awa Traoré', 'email' => 'awa@example.com']);
    $autre = User::factory()->create(['name' => 'Moussa Koné', 'email' => 'moussa@example.com']);

    $this->actingAs($user)->get(route('espace', ['rubrique' => 'compte']))
        ->assertOk()
        ->assertSeeText('Awa Traoré')
        ->assertSeeText('awa@example.com')
        ->assertSeeText('Se déconnecter')
        ->assertDontSeeText('Moussa Koné')
        ->assertDontSeeText('moussa@example.com');
});

it('refuse la suppression du compte avec un mauvais mot de passe', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);

    Livewire::actingAs($user)->test(Espace::class)
        ->set('password', 'faux')
        ->call('deleteAccount')
        ->assertHasErrors(['password']);

    expect(User::find($user->id))->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('refuse la suppression du compte sans mot de passe', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test(Espace::class)
        ->call('deleteAccount')
        ->assertHasErrors(['password']);

    expect(User::find($user->id))->not->toBeNull();
});

it('supprime définitivement le compte avec le bon mot de passe', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);

    Livewire::actingAs($user)->test(Espace::class)
        ->set('password', 'motdepasse1')
        ->call('deleteAccount')
        ->assertRedirect('/');

    expect(User::find($user->id))->toBeNull();
    $this->assertGuest();
    expect(AdminAction::count())->toBe(0);
});

it('inscrit au journal la suppression du compte d’un modérateur, et l’entrée survit au compte', function () {
    $moderator = User::factory()->moderator()->create(['name' => 'Modératrice', 'password' => 'motdepasse1']);

    Livewire::actingAs($moderator)->test(Espace::class)
        ->set('password', 'motdepasse1')
        ->call('deleteAccount');

    expect(User::find($moderator->id))->toBeNull();

    $entry = AdminAction::sole();
    expect($entry->action)->toBe('account.deleted_by_owner')
        ->and($entry->actor_id)->toBe($moderator->id)
        ->and($entry->actor_label)->toBe('Modératrice');
});

it('ne permet à personne d’agir sur Espace sans être connecté et vérifié', function () {
    $this->get('/espace')->assertRedirect(route('login'));

    $this->actingAs(User::factory()->unverified()->create())->get('/espace')
        ->assertRedirect(route('verification.notice'));
});

it('affiche la confirmation avec les états d’erreur du mot de passe', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);

    Livewire::actingAs($user)->test(Espace::class)->call('show', 'compte')
        ->set('password', 'faux')
        ->call('deleteAccount')
        ->assertSee('Le mot de passe est incorrect.');
});

it('efface aussi les jetons de réinitialisation de mot de passe du compte supprimé, et seulement les siens', function () {
    $user = User::factory()->create(['email' => 'awa@example.com', 'password' => 'motdepasse1']);
    $autre = User::factory()->create(['email' => 'moussa@example.com']);

    Password::createToken($user);
    Password::createToken($autre);
    expect(DB::table('password_reset_tokens')->count())->toBe(2);

    Livewire::actingAs($user)->test(Espace::class)
        ->set('password', 'motdepasse1')
        ->call('deleteAccount');

    expect(User::find($user->id))->toBeNull()
        ->and(DB::table('password_reset_tokens')->where('email', 'awa@example.com')->exists())->toBeFalse()
        ->and(DB::table('password_reset_tokens')->where('email', 'moussa@example.com')->exists())->toBeTrue();
});

it('conserve les jetons de réinitialisation quand la suppression est refusée', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);
    Password::createToken($user);

    Livewire::actingAs($user)->test(Espace::class)
        ->set('password', 'faux')
        ->call('deleteAccount')
        ->assertHasErrors(['password']);

    expect(DB::table('password_reset_tokens')->where('email', $user->email)->exists())->toBeTrue();
});

it('efface aussi toutes les sessions du compte supprimé (adresse IP, navigateur), et seulement les siennes', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);
    $autre = User::factory()->create();

    foreach ([['s1', $user->id], ['s2', $user->id], ['s3', $autre->id], ['s4', null]] as [$id, $userId]) {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $userId, 'ip_address' => '203.0.113.9',
            'user_agent' => 'Navigateur de test', 'payload' => '', 'last_activity' => time(),
        ]);
    }

    Livewire::actingAs($user)->test(Espace::class)
        ->set('password', 'motdepasse1')
        ->call('deleteAccount');

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->pluck('id')->sort()->values()->all())->toBe(['s3', 's4']);
});

it('conserve les sessions quand la suppression est refusée', function () {
    $user = User::factory()->create(['password' => 'motdepasse1']);
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    Livewire::actingAs($user)->test(Espace::class)
        ->set('password', 'faux')
        ->call('deleteAccount')
        ->assertHasErrors(['password']);

    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(1);
});
