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

it('active Découvrir sur téléphone et Personnes sur ordinateur, vers le Carrefour Personnes', function () {
    $page = $this->actingAs(User::factory()->create())->get('/fil')->assertOk();

    $page->assertSee('href="'.route('personnes.index').'"', false);

    $html = $this->actingAs(User::factory()->create())->get('/personnes')->assertOk()->getContent();
    preg_match_all('/<a href="[^"]*"\s+aria-current="page"[^>]*>(.*?)<\/a>/s', $html, $actifs);
    $actifs = array_map(fn ($texte) => trim(preg_replace('/\s+/', ' ', strip_tags($texte))), $actifs[1]);

    expect($actifs)->toContain('Découvrir')->toContain('Personnes');
});

it('garde Découvrir actif sur le Monde d’une personne', function () {
    $personne = person('Awa', ['Couture']);
    $html = $this->actingAs(User::factory()->create())->get(route('personnes.show', $personne->profile->public_id))->assertOk()->getContent();

    expect($html)->toMatch('/aria-current="page"[^>]*>\s*<svg.*?<\/svg>\s*Découvrir/s');
});

it('garde masqués Besoins, Projets, Agir et ZUMRA sur le Carrefour, avec ou sans compte', function () {
    foreach ([null, User::factory()->create()] as $user) {
        $page = ($user ? $this->actingAs($user) : $this)->get('/personnes')->assertOk();

        $page->assertDontSeeText('Besoins')->assertDontSeeText('Projets')->assertDontSeeText('Agir')->assertDontSeeText('ZUMRA')
            ->assertDontSeeText('Notifications')->assertDontSeeText('Inviter');
    }
});

it('propose à un visiteur sans compte de se connecter ou de créer un compte, sans la navigation de l’application', function () {
    $this->get('/personnes')->assertOk()
        ->assertSeeText('Se connecter')
        ->assertSeeText('Créer un compte')
        ->assertDontSee('aria-label="Mon espace"', false)
        ->assertDontSeeText('Espace');

    expect($this->get('/personnes')->getContent())->not->toContain('aria-label="Navigation principale"');
});

it('mène les entrées de navigation à des pages qui répondent, pour un visiteur aussi', function () {
    $html = $this->get('/personnes')->getContent();
    preg_match_all('/href="([^"]+)"/', $html, $liens);

    foreach (array_unique($liens[1]) as $href) {
        if (str_starts_with($href, url('/')) && ! str_contains($href, '/build/')) {
            expect($this->get($href)->status())->toBeIn([200, 302], $href);
        }
    }
});
