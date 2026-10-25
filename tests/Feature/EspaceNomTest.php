<?php

use App\Livewire\Espace;
use App\Models\AdminAction;
use App\Models\User;
use App\Services\AdminJournal;
use Livewire\Livewire;

it('affiche le nom actuel dans le formulaire de modification', function () {
    $user = User::factory()->create(['name' => 'Awa Traoré']);

    Livewire::actingAs($user)->test(Espace::class)->call('show', 'compte')
        ->assertSet('name', 'Awa Traoré')
        ->assertSee('Enregistrer le nom');
});

it('permet à chaque personne de modifier son nom affiché, sans espaces superflus', function () {
    $user = User::factory()->create(['name' => 'Awa']);

    Livewire::actingAs($user)->test(Espace::class)
        ->set('name', '  Awa Traoré  ')
        ->call('updateName')
        ->assertHasNoErrors()
        ->assertRedirect(route('espace', ['rubrique' => 'compte']));

    expect($user->fresh()->name)->toBe('Awa Traoré');
});

it('montre le nouveau nom et le message de confirmation sur la page', function () {
    $user = User::factory()->create(['name' => 'Awa']);

    Livewire::actingAs($user)->test(Espace::class)->set('name', 'Koné Djakaridja')->call('updateName');

    $this->actingAs($user->fresh())->withSession(['status' => 'Nom affiché enregistré.'])->get('/espace')
        ->assertSeeText('Koné Djakaridja')
        ->assertSeeText('Nom affiché enregistré.');
});

it('accepte les accents et échappe le nom à l’affichage', function () {
    $user = User::factory()->create(['name' => 'Awa']);

    Livewire::actingAs($user)->test(Espace::class)->set('name', '<b>Zakaria</b> Koné')->call('updateName')->assertHasNoErrors();

    $this->actingAs($user->fresh())->get('/espace')
        ->assertDontSee('<b>Zakaria</b>', false)
        ->assertSee('&lt;b&gt;Zakaria&lt;/b&gt; Koné', false);
});

it('refuse un nom vide, fait d’espaces ou trop long, et ne change rien', function (string $nom) {
    $user = User::factory()->create(['name' => 'Awa']);

    Livewire::actingAs($user)->test(Espace::class)
        ->set('name', $nom)
        ->call('updateName')
        ->assertHasErrors(['name']);

    expect($user->fresh()->name)->toBe('Awa');
})->with([
    'vide' => '',
    'espaces seuls' => '     ',
    'trop long' => str_repeat('é', 256),
]);

it('accepte un nom de 255 caractères', function () {
    $user = User::factory()->create(['name' => 'Awa']);

    Livewire::actingAs($user)->test(Espace::class)->set('name', str_repeat('é', 255))->call('updateName')->assertHasNoErrors();

    expect(mb_strlen($user->fresh()->name))->toBe(255);
});

it('dit « Aucune modification » quand le nom est inchangé, sans rien inscrire au journal', function () {
    $moderator = User::factory()->moderator()->create(['name' => 'Modératrice']);

    Livewire::actingAs($moderator)->test(Espace::class)->call('updateName')->assertRedirect(route('espace', ['rubrique' => 'compte']));

    expect(session('status'))->toBe('Aucune modification.')
        ->and(AdminAction::count())->toBe(0);
});

it('ne modifie jamais le nom de quelqu’un d’autre', function () {
    $user = User::factory()->create(['name' => 'Awa']);
    $autre = User::factory()->create(['name' => 'Moussa']);

    Livewire::actingAs($user)->test(Espace::class)->set('name', 'Nouveau')->call('updateName');

    expect($user->fresh()->name)->toBe('Nouveau')->and($autre->fresh()->name)->toBe('Moussa');
});

it('n’inscrit pas au journal le changement de nom d’un membre ordinaire', function () {
    $user = User::factory()->create(['name' => 'Awa']);

    Livewire::actingAs($user)->test(Espace::class)->set('name', 'Awa Traoré')->call('updateName');

    expect(AdminAction::count())->toBe(0);
});

it('inscrit au journal le changement de nom d’un modérateur ou du super administrateur', function (string $etat) {
    $personne = User::factory()->{$etat}()->create(['name' => 'Ancien Nom']);

    Livewire::actingAs($personne)->test(Espace::class)->set('name', 'Nouveau Nom')->call('updateName');

    $entree = AdminAction::sole();
    expect($entree->action)->toBe('account.renamed_by_owner')
        ->and($entree->actor_id)->toBe($personne->id)
        ->and($entree->actor_label)->toBe('Ancien Nom')
        ->and($entree->subject_id)->toBe($personne->id)
        ->and($entree->payload)->toEqual(['ancien' => 'Ancien Nom', 'nouveau' => 'Nouveau Nom']);
})->with(['moderator', 'superAdmin']);

it('garde l’ancien nom dans les entrées passées du journal, et le nouveau dans les suivantes', function () {
    $moderator = User::factory()->moderator()->create(['name' => 'Ancien Nom']);
    $avant = AdminJournal::record($moderator, 'test.avant');

    Livewire::actingAs($moderator)->test(Espace::class)->set('name', 'Nouveau Nom')->call('updateName');

    $apres = AdminJournal::record($moderator->fresh(), 'test.apres');

    expect(AdminAction::find($avant->id)->actor_label)->toBe('Ancien Nom')
        ->and(AdminAction::find($apres->id)->actor_label)->toBe('Nouveau Nom')
        ->and(AdminAction::where('actor_id', $moderator->id)->pluck('actor_label')->unique()->sort()->values()->all())
        ->toBe(['Ancien Nom', 'Nouveau Nom']);
});

it('conserve l’historique du journal même quand le compte est ensuite supprimé', function () {
    $moderator = User::factory()->moderator()->create(['name' => 'Ancien Nom', 'password' => 'motdepasse1']);

    Livewire::actingAs($moderator)->test(Espace::class)->set('name', 'Nouveau Nom')->call('updateName');
    Livewire::actingAs($moderator->fresh())->test(Espace::class)->set('password', 'motdepasse1')->call('deleteAccount');

    expect(AdminAction::where('actor_id', $moderator->id)->pluck('actor_label')->all())
        ->toBe(['Ancien Nom', 'Nouveau Nom']);
});
