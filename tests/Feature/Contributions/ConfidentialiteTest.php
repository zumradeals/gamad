<?php

use App\Enums\ProposalState;
use App\Livewire\CarrefourContributions;
use App\Livewire\MesContributions;
use App\Livewire\PageProposition;
use App\Livewire\ProposerContribution;
use App\Livewire\PublierBesoin;
use App\Models\ContributionProposal;
use App\Models\ProjectMember;
use App\Models\User;
use App\Services\ContributionFeed;
use App\Services\ContributionProposals;
use App\Services\ContributionStats;
use App\Services\ProjectShowcase;
use App\Services\Publications;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** C1 (D-207) : confidentialité des besoins et des propositions, gel ZUMRA, permissions du lot P1. */
beforeEach(function () {
    c1Module('public');
    c1ModuleZumra('public');
    $this->awa = person('Awa Traoré');
    $this->yao = person('Yao Kouassi');
    $this->moussa = person('Moussa Koné', attributes: ['email' => 'moussa.secret@exemple.org']);
    $this->tiers = person('Troisième Personne');
    $this->zumra = zumra($this->yao, 'Couture solidaire');
    $this->service = app(ContributionProposals::class);
});

function c1Voit($qui, string $classe = CarrefourContributions::class): string
{
    return Livewire::actingAs($qui)->test($classe)->html();
}

it('ne montre jamais une proposition privée dans les cartes, la recherche, les filtres ni les listes des autres', function () {
    $privee = c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id], 'offer' => 'Un secret de couture privé', 'visibility' => 'private']);
    $publique = c1Proposition($this->moussa, ['beneficiary' => ['gamad', null], 'offer' => 'Une offre visible de tous', 'visibility' => 'public', 'kind' => 'time']);

    $feed = app(ContributionFeed::class);
    expect($feed->proposals($this->tiers)->pluck('id')->all())->toBe([$publique->id])
        ->and($feed->proposals($this->tiers, ['q' => 'secret'])->count())->toBe(0)
        ->and($feed->proposals($this->tiers, ['kinds' => ['skills']])->count())->toBe(0);

    $html = c1Voit($this->tiers);
    expect($html)->toContain('Une offre visible de tous')->not->toContain('Un secret de couture privé');

    // La personne qui propose et le bénéficiaire retrouvent la proposition privée dans leur espace, pas sur la page publique.
    expect(c1Voit($this->moussa))->not->toContain('Un secret de couture privé');
    Livewire::actingAs($this->moussa)->test(MesContributions::class)->assertSee('Un secret de couture privé');
    Livewire::actingAs($this->yao)->test(MesContributions::class)->set('tab', 'recues')->assertSee('Un secret de couture privé');
    Livewire::actingAs($this->tiers)->test(MesContributions::class)->assertDontSee('Un secret de couture privé');

    $this->actingAs($this->tiers)->get(route('contributions.show', $privee))->assertNotFound();
    $this->actingAs($this->moussa)->get(route('contributions.show', $privee))->assertOk();
    $this->actingAs($this->yao)->get(route('contributions.show', $privee))->assertOk();
});

it('garde le message et la réponse privés même pour une proposition publique, et n’affiche aucune coordonnée', function () {
    $publique = c1Proposition($this->moussa, ['beneficiary' => ['gamad', null], 'visibility' => 'public', 'message' => 'Mon message confidentiel', 'kind' => 'time']);
    app(ContributionProposals::class)->accept(User::factory()->superAdmin()->create(), $publique, 'Réponse confidentielle');

    $vuParUnTiers = $this->actingAs($this->tiers)->get(route('contributions.show', $publique))->assertOk()->getContent();
    expect($vuParUnTiers)->not->toContain('Mon message confidentiel')->not->toContain('Réponse confidentielle')
        ->not->toContain('moussa.secret@exemple.org')->not->toContain('mailto:')->not->toContain('tel:');

    $vuParLAuteur = $this->actingAs($this->moussa)->get(route('contributions.show', $publique))->assertOk()->getContent();
    expect($vuParLAuteur)->toContain('Mon message confidentiel')->toContain('Réponse confidentielle')->not->toContain('moussa.secret@exemple.org');

    foreach ([c1Voit($this->tiers), c1Voit($this->moussa)] as $html) {
        expect($html)->not->toContain('moussa.secret@exemple.org')->not->toContain('Mon message confidentiel');
    }
});

it('empêche une proposition publique de révéler une personne : privée vers une personne, publique seulement sur un besoin public', function () {
    $this->awa->profile->forceFill(['accepts_contributions' => true])->save();

    expect(fn () => c1Proposition($this->moussa, ['beneficiary' => ['person', $this->awa->id], 'visibility' => 'public']))->toThrow(ValidationException::class, 'reste privée');

    $besoinMembres = c1Besoin($this->awa, ['visibility' => 'members', 'kind' => 'skills']);
    expect(fn () => c1Proposition($this->moussa, ['need' => $besoinMembres, 'visibility' => 'public']))->toThrow(ValidationException::class, 'n’est pas public');

    $besoinPublic = c1Besoin($this->awa, ['title' => 'Un second besoin', 'visibility' => 'public', 'kind' => 'skills']);
    $proposition = c1Proposition($this->moussa, ['need' => $besoinPublic, 'visibility' => 'public']);
    expect(app(ContributionFeed::class)->proposals($this->tiers)->pluck('id')->all())->toBe([$proposition->id]);
});

it('retire de la page publique les propositions retirées ou refusées, et celles d’un besoin devenu invisible', function () {
    $besoin = c1Besoin($this->awa, ['kind' => 'skills']);
    $proposition = c1Proposition($this->moussa, ['need' => $besoin, 'visibility' => 'public']);
    $feed = app(ContributionFeed::class);

    expect($feed->proposals($this->tiers)->count())->toBe(1);

    $this->service->decline($this->awa, $proposition);
    expect($feed->proposals($this->tiers)->count())->toBe(0);
    $this->actingAs($this->tiers)->get(route('contributions.show', $proposition->fresh()))->assertNotFound();

    $autre = c1Proposition(person('Aidant Deux'), ['need' => $besoin, 'visibility' => 'public']);
    $besoin->forceFill(['hidden_at' => now(), 'hidden_by' => $this->yao->id, 'hidden_reason' => 'Test'])->save();
    expect($feed->proposals($this->tiers)->pluck('id')->all())->not->toContain($autre->id);
});

it('masque les besoins privés ou réservés : un besoin « membres » n’existe pas pour un visiteur, un besoin de ZUMRA seulement pour ses membres', function () {
    $besoinMembres = c1Besoin($this->awa, ['title' => 'Besoin réservé aux inscrits', 'visibility' => 'members']);
    $besoinZumra = c1Besoin($this->yao, ['title' => 'Besoin interne ZUMRA', 'visibility' => 'zumra'], ['zumra', $this->zumra->id]);
    $membre = person('Simple Membre');
    ajouteMembre($this->zumra, $membre);
    $feed = app(ContributionFeed::class);

    expect($feed->needs(null)->count())->toBe(0)
        ->and($feed->needs($this->tiers)->pluck('title')->all())->toBe(['Besoin réservé aux inscrits'])
        ->and($feed->needs($membre)->pluck('title')->all())->toContain('Besoin interne ZUMRA');

    $this->actingAs($this->tiers)->get(route('publications.show', $besoinZumra))->assertNotFound();
    expect(fn () => c1Proposition($this->tiers, ['need' => $besoinZumra, 'kind' => 'skills']))->toThrow(ValidationException::class, 'n’existe pas');
});

it('ferme tout le module quand il est désactivé : pages, actions Livewire, politique et navigation', function () {
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['gamad', null], 'visibility' => 'public', 'kind' => 'time']);
    $besoin = c1Besoin($this->awa);
    c1Module('off');

    // Un visiteur sans compte est renvoyé vers la connexion (avant tout composant Livewire de ce test).
    $this->get(route('contributions.index'))->assertRedirect(route('login'));
    $this->get(route('contributions.show', $proposition))->assertRedirect(route('login'));

    foreach (['contributions.index', 'contributions.need', 'contributions.propose', 'espace.mes-contributions'] as $route) {
        $this->actingAs($this->tiers)->get(route($route))->assertNotFound();
    }
    $this->actingAs($this->moussa)->get(route('contributions.show', $proposition))->assertNotFound();
    $this->actingAs($this->tiers)->get(route('fil'))->assertOk()->assertDontSee(route('contributions.index'));
    $this->actingAs($this->tiers)->get(route('agir'))->assertOk()->assertDontSee('data-contributions-entry', false);
    $this->actingAs($this->tiers)->get(route('espace'))->assertOk()->assertDontSee(route('espace.mes-contributions'));

    foreach ([CarrefourContributions::class, PublierBesoin::class, ProposerContribution::class, MesContributions::class] as $classe) {
        Livewire::actingAs($this->tiers)->test($classe)->assertNotFound();
    }
    expect(Gate::forUser($this->moussa)->allows('view', $proposition))->toBeFalse()
        ->and(Gate::forUser($this->tiers)->allows('contribute', $besoin))->toBeFalse();
    // La page d'un besoin ne montre plus aucun accès aux contributions.
    $this->actingAs($this->tiers)->get(route('publications.show', $besoin))->assertOk()->assertDontSee('Proposer une contribution');
});

it('ouvre le test privé aux seuls super administrateurs et testeurs, sans rien exposer aux autres', function () {
    $testeur = person('Testeuse', attributes: ['email' => 'testeuse@exemple.org']);
    $admin = User::factory()->superAdmin()->create();
    c1Module('test', 'testeuse@exemple.org');

    foreach ([$admin, $testeur] as $autorise) {
        $this->actingAs($autorise)->get(route('contributions.index'))->assertOk();
    }
    foreach ([$this->tiers, $this->moussa] as $exclu) {
        $this->actingAs($exclu)->get(route('contributions.index'))->assertNotFound();
        Livewire::actingAs($exclu)->test(CarrefourContributions::class)->assertNotFound();
    }
    $this->actingAs($testeur)->get(route('agir'))->assertSee('data-contributions-entry', false);
});

it('respecte le gel ZUMRA : sans module ZUMRA, ni besoin de ZUMRA ni contribution à une ZUMRA n’apparaissent', function () {
    $besoin = c1Besoin($this->yao, ['title' => 'Besoin de ZUMRA'], ['zumra', $this->zumra->id]);
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id], 'visibility' => 'public']);
    $feed = app(ContributionFeed::class);

    expect($feed->needs($this->tiers)->pluck('id')->all())->toContain($besoin->id)
        ->and($feed->proposals($this->tiers)->pluck('id')->all())->toContain($proposition->id);

    c1ModuleZumra('off');
    expect($feed->needs($this->tiers)->pluck('id')->all())->not->toContain($besoin->id)
        ->and($feed->proposals($this->tiers)->pluck('id')->all())->not->toContain($proposition->id);
    $this->actingAs($this->tiers)->get(route('contributions.show', $proposition))->assertNotFound();
    $this->actingAs($this->yao)->get(route('contributions.show', $proposition))->assertNotFound();
    expect(Gate::forUser($this->tiers)->allows('contribute', $besoin))->toBeFalse();
    Livewire::actingAs($this->yao)->test(PublierBesoin::class)->assertOk()->assertDontSee('Couture solidaire');
    expect(app(ContributionStats::class)->personal($this->yao)['to_handle'])->toBe(0);
});

it('respecte la confidentialité P1 : un projet privé n’existe pas ici, un projet présenté publiquement oui', function () {
    $porteuse = person('Fatou Porteuse');
    $prive = c1Projet($porteuse, $this->zumra, 'Projet discret', publie: false);
    $public = c1Projet(person('Autre Porteuse'), $this->zumra, 'Projet ouvert');

    $besoinPublic = c1Besoin($public->owner, ['title' => 'Un formateur pour le projet ouvert'], ['project', $public->id]);
    $feed = app(ContributionFeed::class);
    expect($feed->needs($this->tiers)->pluck('id')->all())->toContain($besoinPublic->id);

    // Le projet repasse en privé : son besoin disparaît aussitôt de la page, avec ses propositions publiques.
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['project', $public->id], 'visibility' => 'public', 'kind' => 'time']);
    expect($feed->proposals($this->tiers)->pluck('id')->all())->toContain($proposition->id);
    app(ProjectShowcase::class)->unpublish($public->owner, $public->fresh());
    expect($feed->needs($this->tiers)->pluck('id')->all())->not->toContain($besoinPublic->id)
        ->and($feed->proposals($this->tiers)->pluck('id')->all())->not->toContain($proposition->id);
    expect(fn () => c1Proposition(person('Aidant Trois'), ['beneficiary' => ['project', $prive->id]]))->toThrow(ValidationException::class);
});

it('ne donne à la ZUMRA d’accueil aucun pouvoir ni aucune vue sur les propositions d’un projet', function () {
    $porteuse = person('Fatou Porteuse');
    $projet = c1Projet($porteuse, $this->zumra);
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['project', $projet->id], 'kind' => 'time']);

    $this->actingAs($this->yao)->get(route('contributions.show', $proposition))->assertNotFound();
    expect(app(ContributionFeed::class)->handledBy($this->yao)->count())->toBe(0)
        ->and(app(ContributionFeed::class)->handledBy($porteuse)->pluck('id')->all())->toBe([$proposition->id]);
    $this->actingAs($porteuse)->get(route('contributions.show', $proposition))->assertOk();

    $habilite = person('Habilité');
    ajouteMembre($this->zumra, $habilite);
    $member = new ProjectMember(['manages_contributions' => true]);
    $member->project()->associate($projet);
    $member->user()->associate($habilite);
    $member->save();
    $this->actingAs($habilite)->get(route('contributions.show', $proposition))->assertOk();
});

it('compte des faits distincts — intentions, acceptées, réalisées, satisfaits — et anonymise les petits nombres', function () {
    $stats = app(ContributionStats::class);
    expect($stats->community())->toBe(['needs' => 0, 'proposals' => 0, 'accepted' => 0, 'realized' => 0, 'satisfied' => 0]);

    $besoin = c1Besoin($this->awa, ['kind' => 'skills']);
    $un = c1Proposition($this->moussa, ['need' => $besoin]);
    $deux = c1Proposition(person('Aidant Deux'), ['need' => $besoin]);
    $this->service->accept($this->awa, $un);
    $this->service->confirm($this->awa, $un->fresh());
    $this->service->decline($this->awa, $deux);
    app(Publications::class)->resolve($this->awa, $besoin->fresh());

    expect($stats->community())->toBe(['needs' => 1, 'proposals' => 2, 'accepted' => 1, 'realized' => 1, 'satisfied' => 1])
        ->and($stats->personal($this->moussa))->toMatchArray(['proposed' => 1, 'realized' => 1, 'in_progress' => 0, 'needs' => 0])
        ->and($stats->personal($this->awa))->toMatchArray(['needs' => 1, 'proposed' => 0])
        ->and(ContributionStats::anonymised(0))->toBe('0')->and(ContributionStats::anonymised(3))->toBe('< 5')->and(ContributionStats::anonymised(12))->toBe('12');

    // Sous le seuil d'anonymat, la page n'affiche pas le chiffre exact des propositions ; elle ne montre aucun montant.
    $page = c1Voit($this->tiers);
    expect($page)->toContain('&lt; 5')->not->toContain('FCFA')->not->toContain('ZAHAB');
});

it('affiche les mêmes règles sur la page de détail : refus d’une proposition sur besoin clos, et retrait sans donnée', function () {
    $besoin = c1Besoin($this->awa, ['kind' => 'skills']);
    $proposition = c1Proposition($this->moussa, ['need' => $besoin]);

    Livewire::actingAs($this->moussa)->test(PageProposition::class, ['proposal' => $proposition])
        ->call('withdraw')->assertHasNoErrors();
    expect($proposition->fresh()->state)->toBe(ProposalState::Withdrawn);
    expect(ContributionProposal::query()->where('state', 'proposed')->count())->toBe(0);
});
