<?php

use App\Models\User;

it('affiche l’accueil public avec les textes décidés', function () {
    $this->get('/')
        ->assertOk()
        ->assertSeeText('Des personnes. Des actions. Un impact réel.')
        ->assertSeeText('Apportez ce que vous savez faire, apprenez ce qui vous manque, agissez avec d’autres.')
        ->assertSeeText('Formation — Travail — Adoration')
        ->assertSeeText('Créer un compte')
        ->assertSeeText('Se connecter');
});

it('mène les boutons de l’accueil vers de vraies pages', function () {
    $this->get('/')
        ->assertSee('href="'.route('register').'"', false)
        ->assertSee('href="'.route('login').'"', false);

    $this->get(route('register'))->assertOk();
    $this->get(route('login'))->assertOk();
});

it('renvoie une personne connectée vers le Fil', function () {
    $this->actingAs(User::factory()->create())->get('/')->assertRedirect(route('fil'));
});

it('affiche des pages d’erreur en français', function () {
    $this->get('/page-inexistante')->assertNotFound()->assertSeeText('Page introuvable');
});

// ─── Accueil public complet (Lot 5a) ─────────────────────────────────────────

it('explique comment GAMAD marche, avec les quatre idées du réseau et ce qu’il ne fait pas', function () {
    $this->get('/')->assertOk()
        ->assertSeeText('Comment cela marche')->assertSeeText('Vous venez avec ce que vous êtes')->assertSeeText('Des ZUMRA regroupent les personnes')
        ->assertSeeText('Des projets naissent dans les ZUMRA')->assertSeeText('Le Fil montre ce qui se fait')
        ->assertSeeText('Ce que GAMAD ne fait pas')->assertSeeText('Pas de compteur de « j’aime »')->assertSeeText('Votre adresse e-mail et votre téléphone ne sont jamais montrés');
});

it('ne montre aucune section « en ce moment » tant qu’il n’y a rien de réel à montrer', function () {
    $this->get('/')->assertOk()->assertDontSeeText('Ce qui se passe en ce moment')->assertDontSee('aria-label="GAMAD en chiffres"', false);
});

it('montre des chiffres et des exemples réels : personnes vérifiées, ZUMRA et projets, avec des liens publics', function () {
    $yao = person('Yao Kouassi', ['Couture']);
    User::factory()->unverified()->create();
    $zumra = zumra($yao, 'Couture solidaire');
    $projet = projet($yao, $zumra, 'Uniformes de la rentrée');

    $html = $this->get('/')->assertOk()->assertSeeText('Ce qui se passe en ce moment')->assertSeeText('Couture solidaire')->assertSeeText('Uniformes de la rentrée')
        ->assertSeeText('Projet de Couture solidaire')->assertSeeText('Idée')->getContent();

    expect($html)->toContain(route('zumras.show', $zumra))->toContain(route('projets.show', $projet));

    $texte = trim(preg_replace('/\s+/', ' ', strip_tags($html)));
    expect($texte)->toContain('1 personne')->toContain('1 ZUMRA')->toContain('1 projet');
});

it('n’invente aucun chiffre : une catégorie vide n’est pas affichée, et le pluriel suit le compte réel', function () {
    $yao = person('Yao Kouassi', []);
    person('Awa Traoré', []);
    zumra($yao, 'Première ZUMRA');

    preg_match('/<ul[^>]*aria-label="GAMAD en chiffres".*?<\/ul>/s', $this->get('/')->assertOk()->getContent(), $m);
    $chiffres = trim(preg_replace('/\s+/', ' ', strip_tags($m[0])));

    expect($chiffres)->toBe('2 personnes 1 ZUMRA');
});

it('limite les exemples aux trois plus récents, sans montrer d’autre personne que par son nom affiché de ZUMRA', function () {
    $yao = person('Yao Kouassi', []);
    foreach (['Alpha', 'Bravo', 'Charlie', 'Delta'] as $i => $nom) {
        $this->travel(1)->minutes();
        zumra($yao, "ZUMRA $nom");
    }

    $this->get('/')->assertOk()->assertSeeText('ZUMRA Delta')->assertSeeText('ZUMRA Charlie')->assertSeeText('ZUMRA Bravo')->assertDontSeeText('ZUMRA Alpha')
        ->assertDontSeeText('Yao Kouassi')->assertDontSeeText($yao->email);
});
