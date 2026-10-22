<?php

use App\Livewire\Espace;
use App\Models\AdminAction;
use App\Models\User;
use Livewire\Livewire;

it('affiche le nom et l’adresse e-mail de la personne connectée seulement', function () {
    $user = User::factory()->create(['name' => 'Awa Traoré', 'email' => 'awa@example.com']);
    $autre = User::factory()->create(['name' => 'Moussa Koné', 'email' => 'moussa@example.com']);

    $this->actingAs($user)->get('/espace')
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

    Livewire::actingAs($user)->test(Espace::class)
        ->set('password', 'faux')
        ->call('deleteAccount')
        ->assertSee('Le mot de passe est incorrect.');
});
