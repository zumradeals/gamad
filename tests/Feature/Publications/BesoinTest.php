<?php

use App\Enums\NeedState;
use App\Enums\PublicationType;
use App\Livewire\PagePublication;
use App\Models\Gesture;
use App\Models\Publication;
use App\Models\User;
use App\Services\Gestures;
use App\Services\Publications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;
use Livewire\Livewire;

function pageDe(Publication $publication, ?User $user)
{
    return Livewire::actingAs($user ?? new User)->test(PagePublication::class, ['publication' => $publication]);
}

beforeEach(function () {
    $this->auteur = User::factory()->create(['name' => 'Yao Kouassi']);
    $this->besoin = publication($this->auteur, data: ['body' => 'Je cherche quelqu’un qui sait installer un logiciel de caisse.']);
});

it('montre à l’auteur « Mon besoin » : son texte, son état « Ouvert » et la liste des propositions reçues', function () {
    $this->actingAs($this->auteur)->get(route('publications.show', $this->besoin))->assertOk()
        ->assertSeeText('Mon besoin')
        ->assertSeeText('Je cherche quelqu’un qui sait installer un logiciel de caisse.')
        ->assertSeeText('Ouvert')
        ->assertSeeText('Personne n’a encore répondu.')
        ->assertSeeText('Propositions reçues')
        ->assertSeeText('Aucune réponse pour l’instant.')
        ->assertSeeText('Marquer comme résolu')
        ->assertSeeText('Fermer sans suite')
        ->assertSeeText('Vous pouvez accepter plusieurs propositions.');
});

it('liste les propositions reçues avec Accepter et Décliner, et compte les personnes qui proposent leur aide', function () {
    $awa = person('Awa Traoré', ['Informatique'], city: 'Yamoussoukro');
    $seydou = person('Seydou Bamba', ['Gestion de petit commerce'], city: 'Yopougon');
    geste($awa, $this->besoin, 'Je suis libre samedi matin.');
    geste($seydou, $this->besoin, 'Je peux venir samedi après 14 h.');

    $this->actingAs($this->auteur)->get(route('publications.show', $this->besoin))->assertOk()
        ->assertSeeText('2 personnes proposent leur aide')
        ->assertSeeText('Awa Traoré')->assertSeeText('Seydou Bamba')
        ->assertSeeText('Je suis libre samedi matin.')
        ->assertSeeText('Accepter')->assertSeeText('Décliner');
});

it('accepte une proposition : elle passe à « Acceptée » et le besoin à « En cours »', function () {
    $awa = person('Awa Traoré', ['Informatique']);
    $geste = geste($awa, $this->besoin);

    pageDe($this->besoin, $this->auteur)->call('accept', $geste->id)->assertSeeText('Acceptée')->assertSeeText('En cours');

    expect($geste->fresh()->state->value)->toBe('accepted')->and($this->besoin->fresh()->need_state)->toBe(NeedState::InProgress);
});

it('décline une proposition sans changer l’état du besoin', function () {
    $geste = geste(User::factory()->create(), $this->besoin);

    pageDe($this->besoin, $this->auteur)->call('decline', $geste->id)->assertSeeText('Déclinée')->assertDontSeeText('Accepter');

    expect($this->besoin->fresh()->need_state)->toBe(NeedState::Open);
});

it('ne montre pas encore les conversations ni les remerciements, qui viennent dans une autre étape du lot', function () {
    $geste = geste(User::factory()->create(), $this->besoin);
    app(Gestures::class)->accept($this->auteur, $geste);

    $this->actingAs($this->auteur)->get(route('publications.show', $this->besoin))->assertOk()
        ->assertDontSeeText('Ouvrir la conversation')->assertDontSeeText('remercier')->assertDontSeeText('Remerciement')
        ->assertDontSeeText('ouvre une conversation privée');
});

it('ne montre pas les gestes retirés', function () {
    $aidant = person('Awa Traoré', ['Informatique']);
    $geste = geste($aidant, $this->besoin);
    app(Gestures::class)->withdraw($aidant, $geste);

    $this->actingAs($this->auteur)->get(route('publications.show', $this->besoin))->assertDontSeeText('Awa Traoré')->assertSeeText('Aucune réponse pour l’instant.');
});

it('marque le besoin comme résolu, par son auteur seul, et le clôt', function () {
    $geste = geste(User::factory()->create(), $this->besoin);

    pageDe($this->besoin, $this->auteur)->call('resolve')->assertSeeText('Besoin marqué comme résolu.');

    expect($this->besoin->fresh()->need_state)->toBe(NeedState::Resolved);
    $this->actingAs($this->auteur)->get(route('publications.show', $this->besoin))
        ->assertSeeText('Ce besoin est résolu.')->assertDontSeeText('Marquer comme résolu')->assertDontSeeText('Fermer sans suite')->assertDontSeeText('Accepter');
});

it('ferme le besoin sans suite, par son auteur seul', function () {
    pageDe($this->besoin, $this->auteur)->call('close')->assertSeeText('Besoin fermé sans suite.');

    expect($this->besoin->fresh()->need_state)->toBe(NeedState::Closed);
    $this->actingAs($this->auteur)->get(route('publications.show', $this->besoin))->assertSeeText('Ce besoin est fermé sans suite.');
});

it('refuse à toute autre personne de résoudre ou de fermer un besoin', function () {
    $aidant = User::factory()->create();
    geste($aidant, $this->besoin);

    foreach ([User::factory()->create(), User::factory()->moderator()->create(), User::factory()->superAdmin()->create(), $aidant] as $autre) {
        pageDe($this->besoin, $autre)->call('resolve')->assertForbidden();
        pageDe($this->besoin, $autre)->call('close')->assertForbidden();
    }

    expect($this->besoin->fresh()->need_state)->toBe(NeedState::Open);
});

it('ne referme pas un besoin déjà clos : un état final reste final', function () {
    app(Publications::class)->resolve($this->auteur, $this->besoin);

    expect(fn () => app(Publications::class)->close($this->auteur, $this->besoin))->toThrow(AuthorizationException::class)
        ->and(fn () => app(Publications::class)->resolve($this->auteur, $this->besoin))->toThrow(AuthorizationException::class)
        ->and($this->besoin->fresh()->need_state)->toBe(NeedState::Resolved);
});

it('ne donne d’état qu’aux besoins : pas de « résolu » pour un savoir, une demande d’apprendre ou une offre', function () {
    foreach ([PublicationType::Knowledge, PublicationType::LearningWish, PublicationType::Offer] as $type) {
        $p = publication($this->auteur, $type);

        expect(fn () => app(Publications::class)->resolve($this->auteur, $p))->toThrow(AuthorizationException::class);

        $this->actingAs($this->auteur)->get(route('publications.show', $p))->assertOk()
            ->assertDontSeeText('Marquer comme résolu')->assertDontSeeText('Fermer sans suite')->assertDontSeeText('Ouvert');
    }
});

it('adapte l’écran de l’auteur au type : savoir, apprendre, aide', function () {
    $savoir = publication($this->auteur, PublicationType::Knowledge);
    $apprendre = publication($this->auteur, PublicationType::LearningWish);
    $aide = publication($this->auteur, PublicationType::Offer);

    $this->actingAs($this->auteur)->get(route('publications.show', $savoir))->assertSeeText('Mon savoir partagé')->assertSeeText('Personnes qui veulent apprendre');
    $this->actingAs($this->auteur)->get(route('publications.show', $apprendre))->assertSeeText('Personnes qui peuvent transmettre');
    $this->actingAs($this->auteur)->get(route('publications.show', $aide))->assertSeeText('Personnes intéressées');
});

it('permet à l’auteur d’un savoir d’accepter un apprenant, sans état de besoin', function () {
    $savoir = publication($this->auteur, PublicationType::Knowledge);
    $apprenant = User::factory()->create();
    $geste = geste($apprenant, $savoir);

    pageDe($savoir, $this->auteur)->call('accept', $geste->id)->assertSeeText('Acceptée');

    expect($geste->fresh()->state->value)->toBe('accepted')->and($savoir->fresh()->need_state)->toBeNull();
});

it('refuse à la page d’agir sur un geste d’une autre publication ou falsifié', function () {
    $autre = publication($this->auteur);
    $geste = geste(User::factory()->create(), $autre);

    expect(fn () => pageDe($this->besoin, $this->auteur)->call('accept', $geste->id))->toThrow(ModelNotFoundException::class);
    pageDe($this->besoin, $this->auteur)->call('accept', 'garbage')->assertNotFound();
    expect($geste->fresh()->state->value)->toBe('sent');
});

it('montre aux autres la publication seule, avec le bouton de réponse, et rien de la gestion', function () {
    geste(User::factory()->create(), $this->besoin);

    $this->actingAs(User::factory()->create())->get(route('publications.show', $this->besoin))->assertOk()
        ->assertSeeText('Je peux aider')->assertDontSeeText('Propositions reçues')->assertDontSeeText('Accepter')->assertDontSeeText('Marquer comme résolu')
        ->assertSeeText('1 personne propose son aide');
});

it('permet à un visiteur sans compte de lire une publication publique, et lui demande un compte pour répondre', function () {
    $this->get(route('publications.show', $this->besoin))->assertOk()
        ->assertSeeText('Je cherche quelqu’un qui sait installer un logiciel de caisse.')
        ->assertSeeText('Se connecter pour répondre')
        ->assertDontSeeText('Je peux aider')
        ->assertSee(route('login'), false);
});

it('répond 404 à un visiteur pour une publication « inscrits seulement », et 404 pour un identifiant inconnu ou falsifié', function () {
    $discrete = publication($this->auteur, data: ['visibility' => 'members']);

    $this->get(route('publications.show', $discrete))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('publications.show', $discrete))->assertOk();
    $this->get('/publications/'.Str::uuid())->assertNotFound();
    $this->get('/publications/garbage')->assertNotFound();
});

it('cache la publication d’une personne dont l’adresse e-mail n’est pas vérifiée', function () {
    $nonVerifie = User::factory()->unverified()->create();
    $p = new Publication(['type' => PublicationType::Need, 'body' => 'x', 'visibility' => 'public', 'need_state' => 'open']);
    $p->author()->associate($nonVerifie)->save();

    $this->get(route('publications.show', $p))->assertNotFound();
    $this->actingAs(User::factory()->create())->get(route('publications.show', $p))->assertNotFound();
    $this->actingAs($nonVerifie)->get(route('publications.show', $p))->assertOk();
});

it('liste mes publications dans Espace, et seulement les miennes', function () {
    $mienne = publication($this->auteur, data: ['body' => 'Mon texte à moi']);
    publication(User::factory()->create(), data: ['body' => 'Le texte d’un autre']);
    geste(User::factory()->create(), $mienne);

    $this->actingAs($this->auteur)->get('/espace')->assertOk()
        ->assertSeeText('Mes publications')->assertSeeText('Mon texte à moi')->assertSeeText('1 personne propose son aide')
        ->assertDontSeeText('Le texte d’un autre')->assertSee(route('publications.show', $mienne), false);

    $this->actingAs(User::factory()->create())->get('/espace')->assertDontSeeText('Mes publications');
});

it('affiche l’état de son besoin dans Espace quand il n’est plus ouvert', function () {
    app(Publications::class)->resolve($this->auteur, $this->besoin);

    $this->actingAs($this->auteur)->get('/espace')->assertSeeText('Résolu');
});

it('garde le besoin ouvert quand tous les gestes sont déclinés ou retirés', function () {
    $a = geste($u1 = User::factory()->create(), $this->besoin);
    $b = geste($u2 = User::factory()->create(), $this->besoin);
    app(Gestures::class)->decline($this->auteur, $a);
    app(Gestures::class)->withdraw($u2, $b);

    expect($this->besoin->fresh()->need_state)->toBe(NeedState::Open)->and(Gesture::query()->count())->toBe(2);
});
