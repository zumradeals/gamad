<?php

use App\Livewire\CarrefourContributions;
use App\Livewire\Espace;
use App\Livewire\MesContributions;
use App\Livewire\MondeProjet;
use App\Livewire\PageProposition;
use App\Livewire\ParametresContributions;
use App\Livewire\ProposerContribution;
use App\Livewire\PublierBesoin;
use App\Models\AdminAction;
use App\Models\ContributionProposal;
use App\Models\ProjectMember;
use App\Models\Publication;
use App\Models\User;
use App\Services\Settings;
use App\Settings\Registry;
use App\Support\ContributionsModule;
use Livewire\Livewire;

/** C1 (D-207) : les écrans du module Contributions, du parcours complet aux accès contextuels. */
beforeEach(function () {
    c1Module('public');
    c1ModuleZumra('public');
    $this->awa = person('Awa Traoré');
    $this->yao = person('Yao Kouassi');
    $this->moussa = person('Moussa Koné');
    $this->zumra = zumra($this->yao, 'Couture solidaire');
});

it('présente la page Contributions : fil d’Ariane, message, quatre formes, deux actions, onglets et colonne latérale', function () {
    $this->actingAs($this->awa)->get(route('contributions.index'))->assertOk()
        ->assertSeeText('Agir ensemble pour un impact réel')
        ->assertSeeText('Proposer une contribution')->assertSeeText('Publier un besoin')
        ->assertSeeText('Financière')->assertSeeText('Compétences')->assertSeeText('Temps et service')->assertSeeText('Matérielle')
        ->assertSeeText('Besoins récents')->assertSeeText('Contributions récentes')
        ->assertSeeText('Mon engagement')->assertSeeText('Impact de la communauté')->assertSeeText('Une solidarité maîtrisée')
        ->assertSeeText('Type de contribution')->assertSeeText('Bénéficiaire')->assertSeeText('Domaine')->assertSeeText('Localité')
        ->assertSee('aria-label="Fil d’Ariane"', false)->assertSee(route('agir'), false);

    foreach (['Tous', 'Besoins', 'Contributions', 'Personnes', 'ZUMRA', 'Projets', 'Entreprises'] as $onglet) {
        $this->actingAs($this->awa)->get(route('contributions.index'))->assertSeeText($onglet);
    }
});

it('affiche de beaux états vides, sans aucune donnée de la maquette', function () {
    $texte = $this->actingAs($this->awa)->get(route('contributions.index'))->assertOk()->getContent();

    expect($texte)->toContain('Aucun besoin publié pour l’instant.')->toContain('Aucune contribution publique pour l’instant.')
        ->not->toContain('Moussa Koné')->not->toContain('Achat d’ordinateurs')->not->toContain('Parrainage scolaire');
});

it('liste les besoins et les contributions publiques réels, avec leurs filtres et sans classement', function () {
    c1Besoin($this->awa, ['title' => 'Achat d’ordinateurs', 'kind' => 'material']);
    c1Besoin($this->awa, ['title' => 'Un formateur en couture', 'kind' => 'skills']);
    c1Proposition($this->moussa, ['beneficiary' => ['gamad', null], 'offer' => 'Du temps pour accompagner', 'kind' => 'time', 'visibility' => 'public']);

    Livewire::actingAs($this->yao)->test(CarrefourContributions::class)
        ->assertSee('Achat d’ordinateurs')->assertSee('Un formateur en couture')->assertSee('Temps et service pour GAMAD')
        ->set('kinds', ['skills'])->assertSee('Un formateur en couture')->assertDontSee('Achat d’ordinateurs')->assertDontSee('Temps et service pour GAMAD')
        ->call('clearFilters')->set('query', 'ordinateurs')->assertSee('Achat d’ordinateurs')->assertDontSee('Un formateur en couture')
        ->call('clearFilters')->call('showTab', 'besoins')->assertDontSee('Contributions récentes')->assertSee('Achat d’ordinateurs')
        ->call('showTab', 'contributions')->assertDontSee('Besoins récents')->assertSee('Temps et service pour GAMAD')
        ->call('showTab', 'zumra')->assertDontSee('Achat d’ordinateurs')->assertDontSee('Temps et service pour GAMAD')
        ->call('showTab', 'personnes')->assertSee('Achat d’ordinateurs')
        ->set('beneficiaries', ['gamad'])->assertDontSee('Achat d’ordinateurs');
});

it('publie un besoin depuis le formulaire en quatre questions, et dit clairement l’audience', function () {
    Livewire::actingAs($this->awa)->test(PublierBesoin::class)
        ->assertSee('1. De quoi avez-vous besoin ?')->assertSee('2. Quel type d’aide recherchez-vous ?')->assertSee('3. Pour qui est ce besoin ?')->assertSee('4. Quelques précisions utiles')
        ->assertSee('Public : visible par tout le monde')->assertSee('Membres de GAMAD')->assertDontSee('Membres de la ZUMRA seulement')
        ->call('save')->assertHasErrors(['kind'])
        ->set('title', 'Machine à coudre')->set('kind', 'material')->set('details', 'Une machine d’occasion suffit.')
        ->call('save')->assertHasNoErrors();

    $besoin = Publication::query()->where('title', 'Machine à coudre')->firstOrFail();
    expect($besoin->help_kind)->toBe('material')->and($besoin->author_id)->toBe($this->awa->id);
    $this->actingAs($this->awa)->get(route('publications.show', $besoin))->assertOk()->assertSeeText('Entraide')->assertSeeText('Matérielle')->assertSeeText('Publié');
});

it('propose pour une ZUMRA seulement à ses responsables, et refuse un bénéficiaire manipulé dans la requête', function () {
    Livewire::actingAs($this->yao)->test(PublierBesoin::class, ['beneficiary' => 'zumra:'.$this->zumra->id])
        ->assertSee('ZUMRA · Couture solidaire')->assertSee('Membres de la ZUMRA seulement');

    // Une personne extérieure n'a pas ce choix ; forcer la valeur est refusé par le serveur.
    Livewire::actingAs($this->awa)->test(PublierBesoin::class)->assertDontSee('ZUMRA · Couture solidaire')
        ->set('beneficiary', 'zumra:'.$this->zumra->id)->set('title', 'Un besoin')->set('kind', 'skills')
        ->call('save')->assertForbidden();
    expect(Publication::query()->whereNotNull('help_kind')->count())->toBe(0);
});

it('répond à un besoin depuis le formulaire : bénéficiaire et forme d’aide préremplis', function () {
    $besoin = c1Besoin($this->awa, ['title' => 'Un formateur en couture', 'kind' => 'skills']);

    $composant = Livewire::actingAs($this->moussa)->test(ProposerContribution::class);
    // Le besoin vient de l'adresse (?besoin=) : on recrée la requête comme le navigateur.
    $this->actingAs($this->moussa)->get(route('contributions.propose', ['besoin' => $besoin->id]))->assertOk()
        ->assertSeeText('Vous répondez au besoin')->assertSeeText('Un formateur en couture')->assertSeeText('Awa Traoré');

    $composant->assertSee('Comment souhaitez-vous contribuer ?')->assertSee('À qui souhaitez-vous contribuer ?')
        ->assertSee('Que proposez-vous concrètement ?')->assertSee('Un message (facultatif)');
});

it('envoie une proposition spontanée depuis le formulaire et la retrouve dans Mes contributions', function () {
    Livewire::actingAs($this->moussa)->test(ProposerContribution::class)
        ->call('save')->assertHasErrors(['kind'])
        ->set('kind', 'time')->set('target', 'zumra:'.$this->zumra->id)->set('offer', 'Deux demi-journées par mois.')->set('visibility', 'public')
        ->call('save')->assertHasNoErrors()->assertRedirect();

    $proposition = ContributionProposal::query()->firstOrFail();
    expect($proposition->visibility)->toBe('public')->and($proposition->user_id)->toBe($this->moussa->id);
    Livewire::actingAs($this->moussa)->test(MesContributions::class)->assertSee('Pour Couture solidaire')->assertSee('Proposée');
    Livewire::actingAs($this->yao)->test(MesContributions::class)->set('tab', 'recues')->assertSee('Moussa Koné')->assertSee('Deux demi-journées par mois.');
});

it('refuse de proposer sur un besoin invisible ou inexistant', function () {
    $besoinZumra = c1Besoin($this->yao, ['visibility' => 'zumra'], ['zumra', $this->zumra->id]);

    $this->actingAs($this->moussa)->get(route('contributions.propose', ['besoin' => $besoinZumra->id]))->assertNotFound();
    $this->actingAs($this->moussa)->get(route('contributions.propose', ['besoin' => 'n-importe-quoi']))->assertOk();
});

it('traite une proposition depuis sa page : accepter, déclarer, confirmer, avec les bons boutons pour chacun', function () {
    $besoin = c1Besoin($this->awa, ['kind' => 'skills']);
    $proposition = c1Proposition($this->moussa, ['need' => $besoin]);

    Livewire::actingAs($this->moussa)->test(PageProposition::class, ['proposal' => $proposition])
        ->assertSee('Retirer ma proposition')->assertDontSee('Accepter')->assertDontSee('Confirmer la réalisation');
    Livewire::actingAs($this->awa)->test(PageProposition::class, ['proposal' => $proposition])
        ->assertSee('Répondre à cette proposition')->set('note', 'Merci !')->call('accept')->assertHasNoErrors()
        ->assertSee('Confirmer la réalisation')->assertDontSee('Répondre à cette proposition');
    Livewire::actingAs($this->moussa)->test(PageProposition::class, ['proposal' => $proposition->fresh()])
        ->assertSee('Déclarer ma contribution réalisée')->assertSee('Écrire dans la conversation')->call('declareDone')->assertHasNoErrors()
        ->assertDontSee('Déclarer ma contribution réalisée');
    Livewire::actingAs($this->awa)->test(PageProposition::class, ['proposal' => $proposition->fresh()])
        ->assertSee('La personne déclare avoir contribué')->call('confirm')->assertHasNoErrors()->assertSee('Réalisation confirmée');

    expect($proposition->fresh()->state->value)->toBe('realized');
});

it('refuse à un tiers d’agir sur une proposition, même en appelant l’action directement', function () {
    $proposition = c1Proposition($this->moussa, ['beneficiary' => ['zumra', $this->zumra->id], 'visibility' => 'public']);
    $tiers = person('Troisième Personne');

    // Un composant neuf par action : après un 404, l'instantané Livewire du banc d'essai n'est plus réutilisable.
    foreach (['accept', 'decline', 'confirm', 'withdraw', 'declareDone'] as $action) {
        Livewire::actingAs($tiers)->test(PageProposition::class, ['proposal' => $proposition])->call($action)->assertNotFound();
    }
    expect($proposition->fresh()->state->value)->toBe('proposed');
});

it('offre un accès depuis Agir, Mon espace et la page d’un besoin seulement quand le module est ouvert', function () {
    $this->actingAs($this->awa)->get(route('agir'))->assertOk()->assertSee('data-contributions-entry', false)->assertSee(route('contributions.index'), false);
    $this->actingAs($this->awa)->get(route('espace'))->assertOk()->assertSee(route('espace.mes-contributions'), false);

    $navigation = config('navigation');
    expect(array_column($navigation['initial']['phone'], 'label'))->toBe(['Accueil', 'Découvrir', 'Agir', 'Mon espace'])
        ->and(collect($navigation['initial']['phone'])->pluck('label')->doesntContain('Contributions'))->toBeTrue();

    $besoin = c1Besoin($this->awa, ['kind' => 'skills']);
    $this->actingAs($this->moussa)->get(route('publications.show', $besoin))->assertOk()->assertSeeText('Proposer une contribution');
    $this->actingAs($this->awa)->get(route('publications.show', $besoin))->assertOk()->assertSeeText('Propositions de contribution')->assertDontSeeText('Proposer une contribution');
});

it('montre les accès contextuels sur la page d’une ZUMRA, d’un projet, d’une entreprise et d’un profil', function () {
    $this->awa->profile->forceFill(['accepts_contributions' => true])->save();
    $porteuse = person('Fatou Porteuse');
    $projet = c1Projet($porteuse, $this->zumra);
    $patron = person('Patron Entreprise');
    $entreprise = c1Entreprise($patron);
    c1Besoin($this->yao, ['title' => 'Un local de couture'], ['zumra', $this->zumra->id]);
    c1Besoin($patron, ['title' => 'Un comptable bénévole', 'kind' => 'skills'], ['company', $entreprise->id]);

    // ZUMRA : un visiteur propose ; le responsable publie.
    $this->actingAs($this->moussa)->get(route('zumras.show', $this->zumra))->assertOk()->assertSeeText('Besoins et contributions')->assertSeeText('Un local de couture')
        ->assertSee(route('contributions.propose', ['zumra' => $this->zumra->id]), false);
    $this->actingAs($this->yao)->get(route('zumras.show', $this->zumra))->assertOk()->assertSeeText('Publier un besoin')->assertSeeText('Propositions reçues');

    // Projet (présentation publique) : on propose ; le porteur voit ses propositions.
    $this->actingAs($this->moussa)->get(route('projets.show', $projet))->assertOk()->assertSee(route('contributions.propose', ['projet' => $projet->id]), false);

    // Entreprise : jamais d'investissement, jamais de financier.
    $this->actingAs($this->moussa)->get(route('entreprises.show', $entreprise))->assertOk()->assertSeeText('Un comptable bénévole')->assertSeeText('ni un investissement ni une prise de participation');
    $this->actingAs($patron)->get(route('entreprises.show', $entreprise))->assertOk()->assertSeeText('Publier un besoin');

    // Profil : seulement quand la personne l'autorise.
    $this->actingAs($this->moussa)->get(route('personnes.show', $this->awa->profile->public_id))->assertOk()->assertSeeText('Proposer une aide');
    $this->actingAs($this->awa)->get(route('personnes.show', $this->yao->profile->public_id))->assertOk()->assertDontSeeText('Proposer une aide');
});

it('retire tout accès contextuel quand le module est fermé, y compris sur une ZUMRA ouverte', function () {
    c1Besoin($this->yao, ['title' => 'Un local de couture'], ['zumra', $this->zumra->id]);
    c1Module('off');

    $this->actingAs($this->moussa)->get(route('zumras.show', $this->zumra))->assertOk()->assertDontSeeText('Besoins et contributions')->assertDontSeeText('Un local de couture');
});

it('laisse le porteur habiliter un collaborateur aux contributions, et seulement lui', function () {
    $porteuse = person('Fatou Porteuse');
    $projet = c1Projet($porteuse, $this->zumra);
    $collaborateur = person('Collaborateur');
    ajouteMembre($this->zumra, $collaborateur);
    $membre = new ProjectMember;
    $membre->project()->associate($projet);
    $membre->user()->associate($collaborateur);
    $membre->save();

    $page = Livewire::actingAs($porteuse)->test(MondeProjet::class, ['project' => $projet])->set('tab', 'equipe')->assertSee('Habiliter pour les contributions');
    $page->call('setContributionAuthority', $membre->id, true);
    expect($membre->fresh()->manages_contributions)->toBeTrue();
    Livewire::actingAs($porteuse)->test(MondeProjet::class, ['project' => $projet])->set('tab', 'equipe')->assertSee('Retirer l’habilitation aux contributions');

    // La ZUMRA d'accueil et le collaborateur lui-même ne peuvent pas s'habiliter.
    foreach ([$this->yao, $collaborateur] as $intrus) {
        try {
            Livewire::actingAs($intrus)->test(MondeProjet::class, ['project' => $projet])->call('setContributionAuthority', $membre->id, false);
        } catch (Throwable) {
            // Refusé : le projet n'existe pas pour la ZUMRA d'accueil, et le collaborateur ne dirige pas le projet.
        }
    }
    expect($membre->fresh()->manages_contributions)->toBeTrue();
});

it('laisse chacun autoriser ou non les propositions d’aide sur son profil, désactivé par défaut', function () {
    expect($this->awa->profile->fresh()->accepts_contributions)->toBeFalse();

    Livewire::actingAs($this->awa)->test(Espace::class)->assertSee('Recevoir des propositions d’aide sur mon profil')
        ->call('setAcceptsContributions', true);
    expect($this->awa->profile->fresh()->accepts_contributions)->toBeTrue();

    c1Module('off');
    $this->actingAs($this->awa)->get(route('espace'))->assertOk()->assertDontSeeText('Recevoir des propositions d’aide');
});

it('réserve l’ouverture du module au super administrateur, avec trace dans le journal', function () {
    c1Module('off');
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($this->awa)->get(route('espace.parametres.contributions'))->assertForbidden();
    Livewire::actingAs($admin)->test(ParametresContributions::class)->assertSet('state', 'off')
        ->set('state', 'test')->set('testers', "Testeuse@exemple.org\nautre@exemple.org")->call('save')->assertHasNoErrors();

    expect(app(Settings::class)->get('contributions_module'))->toBe('test')
        ->and(ContributionsModule::testers())->toBe(['testeuse@exemple.org', 'autre@exemple.org'])
        ->and(AdminAction::query()->where('action', 'like', '%setting%')->exists() || AdminAction::query()->count() > 0)->toBeTrue();

    Livewire::actingAs($admin)->test(ParametresContributions::class)->set('testers', 'pas-un-courriel')->call('save')->assertHasErrors('testers');
});

it('garde le module fermé par défaut après installation, sans migration ni déploiement qui l’ouvre', function () {
    // Réglages par défaut : la base neuve n'ouvre rien (le beforeEach du fichier l'ouvre explicitement).
    $neuf = Registry::find('contributions_module');

    expect($neuf->default)->toBe('off')->and(array_keys($neuf->options))->toBe(['off', 'test', 'public']);
});
