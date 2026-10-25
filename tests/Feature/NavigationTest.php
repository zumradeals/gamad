<?php

use App\Models\User;
use Illuminate\Testing\TestResponse;

function pageDuFil(): TestResponse
{
    return test()->actingAs(User::factory()->create())->get('/fil');
}

it('n’affiche que Fil, Découvrir, Agir, ZUMRA, Personnes, Besoins, Projets et Espace dans la navigation', function () {
    $html = pageDuFil()->assertOk()->getContent();
    preg_match_all('/<nav aria-label="Navigation principale".*?<\/nav>/s', $html, $navs);
    $texte = trim(preg_replace('/\s+/', ' ', strip_tags(implode(' ', $navs[0]))));

    expect($texte)->toContain('Accueil')->toContain('Découvrir')->toContain('Personnes')->toContain('Besoins')->toContain('Projets')->toContain('Agir')->toContain('Espace')->toContain('ZUMRA')
        ->not->toContain('Rechercher');

    // La cloche des notifications existe depuis le Lot 2b ; la recherche générale vient avec son lot.
    expect($html)->toContain('aria-label="Notifications"')->not->toContain('aria-label="Rechercher"');
});

it('affiche le message d’état vide du Fil, avec un lien vers « Modifier mon profil »', function () {
    pageDuFil()
        ->assertSeeText('Les publications apparaîtront ici. Commencez par compléter votre profil.')
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

it('affiche le menu utilisateur dans la barre d’ordinateur, avec le nom, Mon espace, le profil public et la déconnexion', function () {
    $user = User::factory()->create(['name' => 'zakaria']);

    $this->actingAs($user)->get('/fil')
        ->assertSee('aria-label="Menu de zakaria"', false)
        ->assertSee('href="'.route('espace').'"', false)
        ->assertSeeText('Mon profil public')->assertSeeText('Gérer mon profil')->assertSeeText('Se déconnecter');
});

it('active Découvrir sur téléphone et Personnes sur ordinateur sur le Carrefour Personnes, Découvrir menant à la page des trois choix', function () {
    $page = $this->actingAs(User::factory()->create())->get('/fil')->assertOk();

    $page->assertSee('href="'.route('personnes.index').'"', false)->assertSee('href="'.route('decouvrir').'"', false);

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

it('affiche Besoins dans la barre d’ordinateur d’une personne connectée, dans l’ordre Fil, Personnes, Besoins, Projets, ZUMRA, et rien de la navigation à un visiteur', function () {
    $this->get('/personnes')->assertOk()->assertDontSeeText('Besoins')->assertDontSeeText('Inviter')->assertDontSeeText('Notifications');

    $html = $this->actingAs(User::factory()->create())->get('/personnes')->assertOk()
        ->assertSee('aria-label="Notifications"', false)->assertSee('href="'.route('notifications').'"', false)->getContent();
    preg_match('/<nav aria-label="Navigation principale" class="hidden.*?<\/nav>/s', $html, $barre);
    $texte = trim(preg_replace('/\s+/', ' ', strip_tags($barre[0])));

    expect($texte)->toBe('Accueil Personnes Besoins Projets ZUMRA')->and($barre[0])->toContain('href="'.route('besoins.index').'"');
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

it('active Agir : rond jaune au centre de la barre du bas, bouton jaune dans la barre du haut', function () {
    $html = $this->actingAs(User::factory()->create())->get('/fil')->assertOk()->getContent();

    expect($html)->toContain('id="agir-menu-desktop"')
        ->toContain('id="agir-menu-mobile"')
        ->toContain('x-on:click="agirOpen = true"')
        ->toContain(route('agir.publier', 'besoin'))
        ->toContain(route('agir.publier', 'apprendre'))
        ->toContain(route('agir.publier', 'aide'))
        ->toContain('bg-accent');
});

it('n’offre pas Agir à un visiteur sans compte', function () {
    $this->get('/personnes')->assertOk()->assertDontSee('href="'.route('agir').'"', false);
    $this->get('/agir')->assertRedirect('/login');
    $this->get('/agir/besoin')->assertRedirect('/login');
});
