<?php

use App\Enums\NeedState;
use App\Enums\PublicationType;
use App\Livewire\CarrefourBesoins;
use App\Models\City;
use App\Models\Domain;
use App\Models\Publication;
use App\Models\User;
use App\Services\Moderation;
use App\Services\Publications;
use Livewire\Livewire;

beforeEach(function () {
    $this->awa = person('Awa Traoré');
    $this->yao = person('Yao Kouassi');
    $this->domaines = Domain::query()->orderBy('name')->take(2)->get();
    $this->villes = City::query()->orderBy('name')->take(2)->get();
});

/** Un besoin publié par le chemin réel, au nom d'une personne, d'une ZUMRA ou d'un projet. */
function besoin(User $qui, string $texte = 'Il nous faut deux machines à coudre.', array $data = [], $zumra = null, $projet = null): Publication
{
    return app(Publications::class)->publish($qui, PublicationType::Need, $data + ['body' => $texte], zumra: $zumra, project: $projet);
}

function carrefourBesoins(?User $qui = null)
{
    return $qui ? Livewire::actingAs($qui)->test(CarrefourBesoins::class) : Livewire::test(CarrefourBesoins::class);
}

// ─── Accès et contenu ────────────────────────────────────────────────────────

it('s’ouvre à un visiteur sans compte comme à une personne connectée', function () {
    besoin($this->awa, 'Je cherche un local pour un atelier.');

    $this->get(route('besoins.index'))->assertOk()->assertSeeText('Besoins')->assertSeeText('Je cherche un local pour un atelier.')->assertSeeText('Voir le besoin');
    $this->actingAs($this->yao)->get(route('besoins.index'))->assertOk()->assertSeeText('Je cherche un local pour un atelier.');
});

it('montre l’émetteur de chaque besoin : une personne, une ZUMRA ou un projet', function () {
    $fondateur = person('Fondateur Sylla');
    $zumra = zumra($fondateur, 'Couture solidaire');
    $projet = projet($fondateur, $zumra, 'Uniformes de la rentrée');
    besoin($this->awa, 'Besoin de la personne.');
    besoin($fondateur, 'Besoin de la ZUMRA.', zumra: $zumra);
    besoin($fondateur, 'Besoin du projet.', projet: $projet);

    $page = $this->get(route('besoins.index'))->assertOk();

    $page->assertSeeText('Besoin de la personne.')->assertSeeText('Besoin de la ZUMRA.')->assertSeeText('Besoin du projet.')
        ->assertSeeText('Awa Traoré')->assertSeeText('Couture solidaire')->assertSeeText('Uniformes de la rentrée')
        ->assertSee('href="'.route('zumras.show', $zumra).'"', false)->assertSee('href="'.route('projets.show', $projet).'"', false);
});

it('ne montre que des besoins, jamais un autre type de publication', function () {
    besoin($this->awa, 'Un vrai besoin.');
    publication($this->awa, PublicationType::Knowledge, ['body' => 'Un savoir à partager.']);
    publication($this->awa, PublicationType::LearningWish, ['body' => 'Je veux apprendre.']);
    publication($this->awa, PublicationType::Offer, ['body' => 'Je propose mon aide.']);

    $this->get(route('besoins.index'))->assertSeeText('Un vrai besoin.')->assertDontSeeText('Un savoir à partager.')->assertDontSeeText('Je veux apprendre.')->assertDontSeeText('Je propose mon aide.');

    // Même en demandant tous les états : seul un besoin porte un état.
    carrefourBesoins()->set('state', 'tous')->assertSeeText('Un vrai besoin.')->assertDontSeeText('Un savoir à partager.')->assertDontSeeText('Je veux apprendre.')->assertDontSeeText('Je propose mon aide.');
});

it('applique les règles de visibilité du Fil : niveau « inscrits », niveau « ZUMRA », besoin masqué, auteur suspendu', function () {
    $fondateur = person('Fondateur Sylla');
    $zumra = zumra($fondateur, 'Couture solidaire');
    besoin($this->awa, 'Besoin public.');
    besoin($this->awa, 'Besoin pour inscrits.', ['visibility' => Publication::MEMBERS]);
    besoin($fondateur, 'Besoin réservé aux membres de la ZUMRA.', ['visibility' => Publication::ZUMRA], zumra: $zumra);
    $masque = besoin($this->awa, 'Besoin masqué par la modération.');
    app(Moderation::class)->hide(User::factory()->moderator()->create(), $masque, 'Motif.');

    $visiteur = $this->get(route('besoins.index'));
    $visiteur->assertSeeText('Besoin public.')->assertDontSeeText('Besoin pour inscrits.')->assertDontSeeText('Besoin réservé aux membres')->assertDontSeeText('Besoin masqué');

    $inscrit = $this->actingAs($this->yao)->get(route('besoins.index'));
    $inscrit->assertSeeText('Besoin pour inscrits.')->assertDontSeeText('Besoin réservé aux membres')->assertDontSeeText('Besoin masqué');

    $this->actingAs($fondateur)->get(route('besoins.index'))->assertSeeText('Besoin réservé aux membres de la ZUMRA.')->assertDontSeeText('Besoin masqué');

    // Un besoin masqué n'apparaît jamais dans le Carrefour, pas même à son auteur ni à la modération (comme dans le Fil).
    carrefourBesoins($this->awa)->assertDontSeeText('Besoin masqué par la modération.');
    carrefourBesoins(User::factory()->moderator()->create())->assertDontSeeText('Besoin masqué par la modération.');

    // L'auteur suspendu disparaît, comme du Fil.
    $suspendu = person('Personne Suspendue');
    besoin($suspendu, 'Besoin d’une personne suspendue.');
    $this->get(route('besoins.index'))->assertSeeText('Besoin d’une personne suspendue.');
    $suspendu->forceFill(['suspended_at' => now(), 'suspension_reason' => 'Motif'])->save();
    $this->get(route('besoins.index'))->assertDontSeeText('Besoin d’une personne suspendue.');
});

it('met le besoin le plus récent en premier', function () {
    $vieux = besoin($this->awa, 'Besoin ancien.');
    $vieux->forceFill(['created_at' => now()->subDays(3)])->save();
    besoin($this->awa, 'Besoin récent.');

    carrefourBesoins()->assertSeeInOrder(['Besoin récent.', 'Besoin ancien.']);
});

// ─── États ───────────────────────────────────────────────────────────────────

it('n’affiche par défaut que les besoins ouverts, et propose les autres états', function () {
    besoin($this->awa, 'Besoin ouvert.');
    $enCours = besoin($this->awa, 'Besoin en cours.');
    $enCours->forceFill(['need_state' => NeedState::InProgress])->save();
    $resolu = besoin($this->awa, 'Besoin résolu.');
    $resolu->forceFill(['need_state' => NeedState::Resolved])->save();
    $ferme = besoin($this->awa, 'Besoin fermé sans suite.');
    $ferme->forceFill(['need_state' => NeedState::Closed])->save();

    carrefourBesoins()->assertSeeText('Besoin ouvert.')->assertDontSeeText('Besoin en cours.')->assertDontSeeText('Besoin résolu.')->assertDontSeeText('Besoin fermé sans suite.')
        ->assertSeeText('1 besoin ouvert')->assertSeeText('Ouverts')->assertSee('aria-label="Tous les états"', false)
        ->set('state', 'in_progress')->assertSeeText('Besoin en cours.')->assertDontSeeText('Besoin ouvert.')->assertSeeText('1 besoin correspond à cette recherche')
        ->set('state', 'resolved')->assertSeeText('Besoin résolu.')->assertDontSeeText('Besoin en cours.')
        ->set('state', 'closed')->assertSeeText('Besoin fermé sans suite.')->assertDontSeeText('Besoin ouvert.')->assertDontSeeText('Besoin résolu.')
        ->set('state', 'tous')->assertSeeText('Besoin ouvert.')->assertSeeText('Besoin en cours.')->assertSeeText('Besoin résolu.')->assertSeeText('Besoin fermé sans suite.')->assertSeeText('4 besoins correspondent');
});

it('revient aux besoins ouverts pour un état inconnu', function () {
    besoin($this->awa, 'Besoin ouvert.');
    $resolu = besoin($this->awa, 'Besoin résolu.');
    $resolu->forceFill(['need_state' => NeedState::Resolved])->save();

    carrefourBesoins()->set('state', 'nimporte-quoi')->assertSeeText('Besoin ouvert.')->assertDontSeeText('Besoin résolu.');
});

// ─── Filtres ─────────────────────────────────────────────────────────────────

it('filtre par domaine et par ville', function () {
    besoin($this->awa, 'Besoin du premier domaine.', ['domain_id' => $this->domaines[0]->id, 'city_id' => $this->villes[0]->id]);
    besoin($this->awa, 'Besoin du second domaine.', ['domain_id' => $this->domaines[1]->id, 'city_id' => $this->villes[1]->id]);

    carrefourBesoins()->set('domain', $this->domaines[0]->id)->assertSeeText('Besoin du premier domaine.')->assertDontSeeText('Besoin du second domaine.')
        ->set('domain', '')->set('city', $this->villes[1]->id)->assertSeeText('Besoin du second domaine.')->assertDontSeeText('Besoin du premier domaine.')
        ->set('city', $this->villes[1]->id)->set('domain', $this->domaines[0]->id)->assertSeeText('Aucun besoin ne correspond à cette recherche.');
});

it('filtre par émetteur : personnes, ZUMRA, projets', function () {
    $fondateur = person('Fondateur Sylla');
    $zumra = zumra($fondateur, 'Couture solidaire');
    $projet = projet($fondateur, $zumra, 'Uniformes de la rentrée');
    besoin($this->awa, 'Besoin de la personne.');
    besoin($fondateur, 'Besoin de la ZUMRA.', zumra: $zumra);
    besoin($fondateur, 'Besoin du projet.', projet: $projet);

    carrefourBesoins()->set('emitter', 'personne')->assertSeeText('Besoin de la personne.')->assertDontSeeText('Besoin de la ZUMRA.')->assertDontSeeText('Besoin du projet.')
        ->set('emitter', 'zumra')->assertSeeText('Besoin de la ZUMRA.')->assertDontSeeText('Besoin de la personne.')->assertDontSeeText('Besoin du projet.')
        ->set('emitter', 'projet')->assertSeeText('Besoin du projet.')->assertDontSeeText('Besoin de la ZUMRA.')->assertDontSeeText('Besoin de la personne.')
        ->set('emitter', 'organisation')->assertSeeText('Besoin de la personne.')->assertSeeText('Besoin de la ZUMRA.')->assertSeeText('Besoin du projet.');
});

it('ignore un identifiant de domaine ou de ville qui n’en est pas un', function () {
    besoin($this->awa, 'Besoin quelconque.');

    carrefourBesoins()->set('domain', 'pas-un-uuid')->set('city', "'; drop table users; --")->assertSeeText('Besoin quelconque.');
});

// ─── Recherche ───────────────────────────────────────────────────────────────

it('cherche dans le texte du besoin, sans tenir compte de la casse', function () {
    besoin($this->awa, 'Il nous faut deux machines à coudre.');
    besoin($this->awa, 'Nous cherchons un local.');

    carrefourBesoins()->set('query', 'MACHINES')->assertSeeText('machines à coudre')->assertDontSeeText('un local')->assertSeeText('1 besoin correspond à cette recherche');
});

it('cherche par capacité demandée, sans tenir compte des accents ni de la casse', function () {
    besoin($this->awa, 'Un besoin de plomberie.', ['skills' => ['Électricité']]);
    besoin($this->awa, 'Un autre besoin.', ['skills' => ['Comptabilité']]);

    carrefourBesoins()->set('query', 'electricite')->assertSeeText('Un besoin de plomberie.')->assertDontSeeText('Un autre besoin.');
});

it('traite les caractères spéciaux de la recherche comme du texte', function () {
    besoin($this->awa, 'Un besoin ordinaire.');
    besoin($this->awa, 'Un besoin à 100% bénévole.');

    carrefourBesoins()->set('query', '%')->assertDontSeeText('Un besoin ordinaire.')->assertSeeText('100% bénévole')
        ->set('query', '_')->assertDontSeeText('Un besoin ordinaire.')->assertSeeText('Aucun besoin ne correspond à cette recherche.');
});

it('efface la recherche et les filtres, et revient aux besoins ouverts', function () {
    besoin($this->awa, 'Besoin ouvert.');

    carrefourBesoins()->set('query', 'zzz')->set('state', 'tous')->set('emitter', 'projet')
        ->assertSeeText('Aucun besoin ne correspond à cette recherche.')->assertSeeText('Effacer la recherche')
        ->call('clearFilters')->assertSet('query', '')->assertSet('state', 'open')->assertSet('emitter', '')->assertSeeText('Besoin ouvert.');
});

// ─── États vides, pagination ─────────────────────────────────────────────────

it('affiche l’état vide, avec un chemin pour publier selon que l’on est connecté ou non', function () {
    $this->get(route('besoins.index'))->assertSeeText('Aucun besoin ouvert pour l’instant.')->assertSeeText('Créer un compte')->assertSee('href="'.route('register').'"', false);
    $this->actingAs($this->yao)->get(route('besoins.index'))->assertSeeText('Aucun besoin ouvert pour l’instant.')->assertSee('href="'.route('agir').'"', false);
});

it('pagine par 10 besoins', function () {
    foreach (range(1, 12) as $i) {
        $b = besoin($this->awa, "Besoin numéro $i.");
        $b->forceFill(['created_at' => now()->subMinutes(30 - $i)])->save();
    }

    carrefourBesoins()->assertSeeText('Page 1 sur 2')->assertSeeText('Besoin numéro 12.')->assertSeeText('Besoin numéro 3.')->assertDontSeeText('Besoin numéro 2.')
        ->call('nextPage')->assertSeeText('Page 2 sur 2')->assertSeeText('Besoin numéro 2.')->assertSeeText('Besoin numéro 1.')->assertDontSeeText('Besoin numéro 12.');
});

// ─── La carte : un seul bouton, vers la publication ──────────────────────────

it('mène chaque besoin à sa publication, sans bouton de geste ni « Signaler » dans le Carrefour', function () {
    $besoin = besoin($this->awa, 'Besoin à ouvrir.');

    $html = $this->actingAs($this->yao)->get(route('besoins.index'))->assertOk()->getContent();

    expect($html)->toContain('href="'.route('publications.show', $besoin).'"')->toContain('Voir le besoin')
        ->not->toContain('open-gesture')->not->toContain('Je peux aider')->not->toContain('Signaler')->not->toContain('Se connecter pour répondre');

    $this->get(route('besoins.index'))->assertDontSee('Se connecter pour répondre');

    // La publication, elle, garde le geste.
    $this->actingAs($this->yao)->get(route('publications.show', $besoin))->assertOk();
});

it('dit « Voir mon besoin » à son auteur', function () {
    besoin($this->awa, 'Mon propre besoin.');

    $this->actingAs($this->awa)->get(route('besoins.index'))->assertSeeText('Voir mon besoin')->assertDontSeeText('Voir le besoin');
});

// ─── Découvrir ───────────────────────────────────────────────────────────────

it('offre la page Découvrir avec les trois choix, pour un visiteur comme pour une personne connectée', function () {
    foreach ([null, $this->yao] as $qui) {
        $page = ($qui ? $this->actingAs($qui) : $this)->get(route('decouvrir'))->assertOk();

        $page->assertSeeText('Que cherchez-vous ?')->assertSeeText('Trouvez une personne, un besoin concret ou un projet à découvrir.')
            ->assertSeeInOrder(['Personnes', 'Besoins', 'Projets'])
            ->assertSee('href="'.route('personnes.index').'"', false)->assertSee('href="'.route('besoins.index').'"', false)->assertSee('href="'.route('projets.index').'"', false);
    }
});

it('ouvre sur téléphone une fenêtre à trois choix depuis « Découvrir », qui reste un lien vers la page sans JavaScript', function () {
    $html = $this->actingAs($this->yao)->get(route('fil'))->assertOk()->getContent();

    // Le lien « Découvrir » de la barre du bas : vers la page, avec la fenêtre en renfort.
    preg_match('/<a href="'.preg_quote(route('decouvrir'), '/').'"[^>]*x-on:click\.prevent="discover = true"[^>]*>/', $html, $lien);
    expect($lien)->not->toBeEmpty()->and($lien[0])->toContain('aria-haspopup="dialog"');

    // La fenêtre : cachée par défaut, un titre, un bouton de fermeture, trois choix.
    preg_match('/<div x-show="discover" x-cloak.*?role="dialog".*?<\/ul>/s', $html, $fenetre);
    expect($fenetre)->not->toBeEmpty()->and($fenetre[0])->toContain('aria-modal="true"')->toContain('aria-labelledby="decouvrir-titre"')->toContain('aria-label="Fermer"')
        ->toContain('Que cherchez-vous ?')->toContain('href="'.route('personnes.index').'"')->toContain('href="'.route('besoins.index').'"')->toContain('href="'.route('projets.index').'"');
    expect(strpos($fenetre[0], 'Personnes'))->toBeLessThan(strpos($fenetre[0], 'Besoins'))->and(strpos($fenetre[0], 'Besoins'))->toBeLessThan(strpos($fenetre[0], 'Projets'));
});

it('n’offre pas la fenêtre Découvrir à un visiteur sans compte, qui n’a pas la barre du bas', function () {
    $this->get(route('personnes.index'))->assertOk()->assertDontSee('decouvrir-titre', false)->assertDontSee('discover = true', false);
});

it('active Découvrir sur les trois Carrefours et sur sa page', function () {
    foreach (['personnes.index', 'besoins.index', 'projets.index', 'decouvrir'] as $route) {
        $html = $this->actingAs($this->yao)->get(route($route))->assertOk()->getContent();

        expect($html)->toMatch('/<a href="'.preg_quote(route('decouvrir'), '/').'"\s+aria-current="page"/');
    }

    $html = $this->actingAs($this->yao)->get(route('zumras.index'))->getContent();
    expect($html)->not->toMatch('/<a href="'.preg_quote(route('decouvrir'), '/').'"\s+aria-current="page"/');
});

it('ajoute Besoins aux accès « Explorer GAMAD » du Fil', function () {
    $this->actingAs($this->yao)->get(route('fil'))->assertSee('href="'.route('besoins.index').'"', false)->assertSeeText('Voir ce qui manque');
});

it('lit les trois choix de Découvrir dans la configuration, dans l’ordre Personnes, Besoins, Projets', function () {
    expect(collect(config('navigation.discover'))->pluck('label')->all())->toBe(['Personnes', 'Besoins', 'Projets'])
        ->and(collect(config('navigation.discover'))->pluck('route')->all())->toBe(['personnes.index', 'besoins.index', 'projets.index']);
});
