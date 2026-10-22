<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;

function pageDuFil(): TestResponse
{
    return test()->actingAs(User::factory()->create())->get('/fil');
}

it('n’affiche que Fil, Découvrir, Personnes et Espace dans la navigation', function () {
    pageDuFil()
        ->assertOk()
        ->assertSeeText('Fil')
        ->assertSeeText('Découvrir')
        ->assertSeeText('Personnes')
        ->assertSeeText('Espace')
        ->assertDontSeeText('Agir')
        ->assertDontSeeText('ZUMRA')
        ->assertDontSeeText('Besoins')
        ->assertDontSeeText('Projets')
        ->assertDontSeeText('Rechercher')
        ->assertDontSeeText('Notifications');
});

it('affiche le message d’état vide du Fil, avec un lien vers « Modifier mon profil »', function () {
    pageDuFil()
        ->assertSeeText('Le Fil se remplira bientôt. Commencez par compléter votre profil.')
        ->assertSee('href="'.route('espace.profil').'"', false);
});

it('mène chaque entrée affichée vers une page qui répond', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $html = $this->get('/fil')->getContent();
    preg_match_all('/<nav aria-label="Navigation principale".*?<\/nav>/s', $html, $navs);
    preg_match_all('/href="([^"]+)"/', implode($navs[0]), $links);

    expect(array_unique($links[1]))->not->toBeEmpty();

    foreach (array_unique($links[1]) as $href) {
        $this->get($href)->assertOk();
    }
});

it('pilote la navigation par la configuration', function () {
    config(['navigation.phone.1.enabled' => true, 'navigation.phone.1.route' => 'fil']);

    pageDuFil()->assertSeeText('Découvrir');
});

it('affiche l’initiale de la personne dans la barre d’ordinateur, qui mène à Espace', function () {
    $user = User::factory()->create(['name' => 'zakaria']);

    $this->actingAs($user)->get('/fil')
        ->assertSee('aria-label="Mon espace"', false)
        ->assertSee('href="'.route('espace').'"', false);
});
