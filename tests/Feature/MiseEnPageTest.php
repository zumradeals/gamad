<?php

// Mise en page sur ordinateur (D-143) : les pages ne sont plus isolées au milieu de l'écran. Les pages de consultation prennent la
// largeur du Fil (1 240 px) ; les formulaires et écrans de saisie une largeur de lecture (920 px). Le téléphone ne change pas.

function classes_du_contenu(string $html): string
{
    preg_match('/<main\s+class="([^"]*)"/', $html, $m);

    return $m[1] ?? '';
}

beforeEach(function () {
    $this->yao = person('Yao Kouassi', ['Couture']);
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->projet = projet($this->yao, $this->zumra, 'Uniformes de la rentrée');
});

it('donne aux pages de consultation la largeur du Fil', function () {
    $pages = [
        'Fil' => route('fil'),
        'Carrefour Personnes' => route('personnes.index'),
        'Monde d’une personne' => route('personnes.show', $this->yao->profile->public_id),
        'Carrefour ZUMRA' => route('zumras.index'),
        'Monde d’une ZUMRA' => route('zumras.show', $this->zumra),
        'Carrefour Projets' => route('projets.index'),
        'Monde d’un projet' => route('projets.show', $this->projet),
        'Espace' => route('espace'),
    ];

    foreach ($pages as $nom => $url) {
        $classes = classes_du_contenu($this->actingAs($this->yao)->get($url)->assertOk()->getContent());

        // Décision du Président : seule la page Personnes est élargie (1480 px), pour ses trois colonnes.
        $this->assertStringContainsString($nom === 'Carrefour Personnes' ? 'max-w-[1480px]' : 'max-w-[1240px]', $classes, $nom);
        $this->assertStringNotContainsString('max-w-[920px]', $classes, $nom);
        $this->assertStringNotContainsString('max-w-[760px]', $classes, $nom);
    }
});

it('donne aux formulaires et écrans de saisie une largeur de lecture, plus large qu’avant', function () {
    $pages = [
        'Agir' => route('agir'),
        'Publier' => route('agir.publier', 'besoin'),
        'Créer une ZUMRA' => route('zumras.create'),
        'Modifier une ZUMRA' => route('zumras.edit', $this->zumra),
        'Créer un projet' => route('projets.create', $this->zumra),
        'Modifier mon profil' => route('espace.profil'),
        'Notifications' => route('notifications'),
    ];

    foreach ($pages as $nom => $url) {
        $classes = classes_du_contenu($this->actingAs($this->yao)->get($url)->assertOk()->getContent());

        $this->assertStringContainsString('max-w-[920px]', $classes, $nom);
        $this->assertStringNotContainsString('max-w-[1240px]', $classes, $nom);
        $this->assertStringNotContainsString('max-w-[760px]', $classes, $nom);
    }
});

it('garde la largeur de lecture pour un visiteur sans compte sur les pages publiques larges', function () {
    foreach ([route('zumras.index'), route('projets.index'), route('projets.show', $this->projet), route('zumras.show', $this->zumra)] as $url) {
        expect(classes_du_contenu($this->get($url)->assertOk()->getContent()))->toContain('max-w-[1240px]');
    }
});

it('range les cartes des carrefours en colonnes sur ordinateur, et en une seule sur téléphone', function () {
    $personne = person('Awa Traoré', ['Broderie']);

    // Carrefours éditoriaux validés en D-162, D-180 et D-181 : deux colonnes lisibles.
    foreach ([route('zumras.index'), route('projets.index'), route('besoins.index')] as $url) {
        expect($this->actingAs($personne)->get($url)->assertOk()->getContent())
            ->toContain('grid gap-5 lg:grid-cols-2')->not->toContain('xl:grid-cols-3');
    }

    expect($this->actingAs($personne)->get(route('personnes.index'))->assertOk()->getContent())
        ->toContain('xl:grid-cols-[255px_minmax(0,1fr)_340px]')
        ->toContain('person-discovery-card');
});

it('garde un marquage identique sur téléphone : marges de 16 px et bandeau jusqu’au bord', function () {
    $html = $this->actingAs($this->yao)->get(route('zumras.show', $this->zumra))->assertOk()->getContent();

    expect(classes_du_contenu($html))->toContain('px-4')->toContain('md:px-6')
        ->and($html)->toContain('-mx-4 -mt-4');
});
