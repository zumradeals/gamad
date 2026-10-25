<?php

use App\Livewire\Espace;
use App\Models\User;
use App\Services\Gestures;
use App\Services\Gratitudes;
use App\Services\Publications;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->auteur = person('Yao Kouassi', ['Couture']);
    $this->aidant = person('Awa Traoré', ['Informatique']);
    $this->besoin = publication($this->auteur, data: ['body' => 'Logiciel de caisse pour ma boutique']);
});

function espace(User $qui)
{
    return Livewire::actingAs($qui)->test(Espace::class);
}

it('dit simplement qu’il n’y a rien à faire quand il n’y a rien', function () {
    // Rien à faire : le bloc n'est pas affiché du tout (seules les actions réelles apparaissent).
    espace($this->auteur)->assertDontSeeText('À faire maintenant')->assertDontSeeText('Rien ne vous attend')->assertDontSeeText('réponse à traiter')->assertDontSeeText('non lu');
});

it('montre les réponses à traiter, une ligne par publication, et la retire une fois traitées', function () {
    geste($this->aidant, $this->besoin);
    geste(User::factory()->create(), $this->besoin);

    espace($this->auteur)->assertSeeText('2 réponses à traiter')->assertSeeText('Sur votre publication « Logiciel de caisse')
        ->assertSee(route('publications.show', $this->besoin), false);

    foreach ($this->besoin->gestures as $geste) {
        app(Gestures::class)->decline($this->auteur, $geste);
    }

    espace($this->auteur)->assertDontSeeText('réponses à traiter')->assertDontSeeText('À faire maintenant');
});

it('ne montre pas les réponses en attente d’un besoin clos', function () {
    geste($this->aidant, $this->besoin);
    app(Publications::class)->close($this->auteur, $this->besoin);

    espace($this->auteur)->assertDontSeeText('à traiter');
});

it('ne montre les réponses reçues qu’à l’auteur de la publication', function () {
    geste($this->aidant, $this->besoin);

    espace($this->aidant)->assertDontSeeText('à traiter');
});

it('montre les messages non lus, avec le nom de la personne, et la ligne disparaît à la lecture', function () {
    $conversation = conversationDe($this->auteur, $this->aidant, $this->besoin);
    ecrire($this->aidant, $conversation, 'Bonjour');
    ecrire($this->aidant, $conversation, 'Vous êtes là ?');

    espace($this->auteur)->assertSeeText('2 messages non lus')->assertSeeText('Awa Traoré')->assertSee(route('conversations.show', $conversation), false);
    // Celui qui a écrit n'a rien à lire.
    espace($this->aidant)->assertDontSeeText('non lu');

    pageConversation($this->auteur, $conversation);

    espace($this->auteur)->assertDontSeeText('non lu');
});

it('propose de remercier un besoin résolu depuis moins de 7 jours, sans remerciement', function () {
    conversationDe($this->auteur, $this->aidant, $this->besoin);
    app(Publications::class)->resolve($this->auteur, $this->besoin);

    espace($this->auteur)->assertSeeText('Remercier Awa Traoré ?')->assertSeeText('Votre besoin est résolu depuis aujourd’hui');

    Carbon::setTestNow(now()->addDays(2));
    espace($this->auteur)->assertSeeText('résolu depuis 2 jours');

    // Passé 7 jours, la liste n'insiste plus (on peut toujours remercier depuis le besoin).
    Carbon::setTestNow(now()->addDays(6));
    espace($this->auteur)->assertDontSeeText('Remercier');
    Carbon::setTestNow();
});

it('retire la ligne « Remercier » une fois le remerciement publié', function () {
    conversationDe($this->auteur, $this->aidant, $this->besoin);
    app(Publications::class)->resolve($this->auteur, $this->besoin);
    espace($this->auteur)->assertSeeText('Remercier Awa Traoré ?');

    app(Gratitudes::class)->thank($this->auteur, $this->besoin->fresh(), $this->aidant->id, 'Merci Awa');

    espace($this->auteur)->assertDontSeeText('Remercier Awa');
});

it('ne propose pas de remercier quand aucune proposition n’a été acceptée, ni un besoin fermé sans suite', function () {
    $sansAide = publication($this->auteur, data: ['body' => 'Sans aide']);
    app(Publications::class)->resolve($this->auteur, $sansAide);
    $ferme = publication($this->auteur, data: ['body' => 'Fermé']);
    conversationDe($this->auteur, $this->aidant, $ferme);
    app(Publications::class)->close($this->auteur, $ferme);

    espace($this->auteur)->assertDontSeeText('Remercier');
});

it('ne propose de remercier qu’à l’auteur du besoin', function () {
    conversationDe($this->auteur, $this->aidant, $this->besoin);
    app(Publications::class)->resolve($this->auteur, $this->besoin);

    espace($this->aidant)->assertDontSeeText('Remercier');
});

it('affiche le nombre de lignes à faire en en-tête, sans compter de personnes', function () {
    geste($this->aidant, $this->besoin);
    $conversation = conversationDe($this->auteur, $this->aidant, publication($this->auteur, data: ['body' => 'Autre']));
    ecrire($this->aidant, $conversation, 'Bonjour');

    espace($this->auteur)->assertSee('aria-label="2 à faire"', false);
});

it('n’affiche aucune ligne de modération : elle vient avec la PR 2c', function () {
    espace($this->auteur)->assertDontSeeText('masquée')->assertDontSeeText('Expliquer');
});
