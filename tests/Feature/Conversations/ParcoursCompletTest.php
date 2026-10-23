<?php

use App\Livewire\Espace;
use App\Livewire\Fil;
use App\Livewire\GestureSheet;
use App\Livewire\NotificationBell;
use App\Livewire\PageConversation;
use App\Livewire\PageNotifications;
use App\Livewire\PagePublication;
use App\Livewire\PublierPublication;
use App\Models\Gesture;
use App\Models\Publication;
use App\Models\User;
use Livewire\Livewire;

/**
 * Les deux parcours du Lot 2 de bout en bout (critères de fin), par les écrans, dans l'ordre qu'une vraie personne suivrait,
 * avec les notifications, « À faire maintenant » et la conversation entre les deux.
 */
it('parcours « J’ai besoin » → « Je peux aider » → acceptation → conversation → remerciement', function () {
    $yao = person('Yao Kouassi', ['Gestion de projet'], city: 'Cocody');
    $awa = person('Awa Traoré', ['Informatique', 'Tableur Excel'], city: 'Yamoussoukro');

    // 1. Yao publie son besoin.
    Livewire::actingAs($yao)->test(PublierPublication::class, ['slug' => 'besoin'])
        ->set('body', 'Je cherche quelqu’un qui sait installer un logiciel de caisse pour ma boutique à Cocody.')->call('publish');
    $besoin = Publication::query()->where('author_id', $yao->id)->firstOrFail();

    // 2. Awa propose son aide. Yao est prévenu (cloche) et la ligne apparaît dans « À faire maintenant ».
    Livewire::actingAs($awa)->test(GestureSheet::class)->dispatch('open-gesture', id: $besoin->id)
        ->set('message', 'Je suis libre samedi matin.')->call('send')->assertHasNoErrors();
    Livewire::actingAs($yao)->test(NotificationBell::class)->assertSee('1 non lue');
    Livewire::actingAs($yao)->test(PageNotifications::class)->assertSeeText('Awa Traoré')->assertSeeText('a répondu à votre publication');
    Livewire::actingAs($yao)->test(Espace::class)->assertSeeText('1 réponse à traiter');

    // 3. Yao accepte : la conversation s'ouvre, Awa est prévenue.
    $geste = Gesture::query()->firstOrFail();
    Livewire::actingAs($yao)->test(PagePublication::class, ['publication' => $besoin])->call('accept', $geste->id)->assertSeeText('Écrire');
    $conversation = $geste->fresh()->conversation;
    expect($conversation)->not->toBeNull();
    Livewire::actingAs($awa)->test(PageNotifications::class)->assertSeeText('Yao Kouassi')->assertSeeText('a accepté votre réponse');
    Livewire::actingAs($awa)->test(Fil::class)->assertSeeText('Ouvrir la conversation');

    // 4. Ils s'écrivent. Chacun est prévenu, lit, répond.
    Livewire::actingAs($yao)->test(PageConversation::class, ['conversation' => $conversation])
        ->set('body', 'Bonjour Awa, merci d’avoir proposé votre aide.')->call('send')->assertHasNoErrors();
    Livewire::actingAs($awa)->test(Espace::class)->assertSeeText('1 message non lu');
    Livewire::actingAs($awa)->test(PageConversation::class, ['conversation' => $conversation])
        ->assertSeeText('Bonjour Awa')->set('body', 'Avec plaisir. Venez vers 9 h 30 samedi.')->call('send');
    Livewire::actingAs($awa)->test(Espace::class)->assertSeeText('Rien ne vous attend');
    Livewire::actingAs($yao)->test(PageConversation::class, ['conversation' => $conversation])->assertSeeText('Avec plaisir');

    // 5. Yao marque son besoin résolu et remercie Awa : le remerciement est public sur son profil.
    Livewire::actingAs($yao)->test(PagePublication::class, ['publication' => $besoin])
        ->set('helperId', $awa->id)->set('gratitudeText', 'Awa a installé le logiciel et m’a formé en une matinée.')->call('resolveAndThank')->assertHasNoErrors();
    expect($besoin->fresh()->need_state->value)->toBe('resolved');

    Livewire::actingAs($awa)->test(PageNotifications::class)->assertSeeText('vous remercie sur votre profil');
    app('auth')->forgetGuards();
    $this->get(route('personnes.show', $awa->profile->public_id))->assertOk()
        ->assertSeeText('Remerciements reçus')->assertSeeText('Awa a installé le logiciel et m’a formé en une matinée.')->assertSeeText('Yao Kouassi');

    // 6. La ligne « Remercier » n'existe plus pour Yao.
    Livewire::actingAs($yao)->test(Espace::class)->assertDontSeeText('Remercier');
});

it('parcours « Je partage un savoir » → « Je veux apprendre » → apprenant accepté → conversation', function () {
    $awa = person('Awa Traoré', ['Comptabilité'], city: 'Yamoussoukro');
    $moussa = person('Moussa Koné', [], ['Comptabilité'], city: 'Bouaké');

    Livewire::actingAs($awa)->test(PublierPublication::class, ['slug' => 'savoir'])
        ->set('body', 'Je montre comment tenir un cahier de caisse simple.')->call('publish');
    $savoir = Publication::query()->where('author_id', $awa->id)->firstOrFail();

    Livewire::actingAs($moussa)->test(Fil::class)->call('setFilter', 'savoirs')->assertSeeText('cahier de caisse')->assertSeeText('Je veux apprendre');
    Livewire::actingAs($moussa)->test(GestureSheet::class)->dispatch('open-gesture', id: $savoir->id)->call('send')->assertHasNoErrors();

    $geste = Gesture::query()->firstOrFail();
    Livewire::actingAs($awa)->test(PagePublication::class, ['publication' => $savoir])->call('accept', $geste->id);

    $conversation = $geste->fresh()->conversation;
    Livewire::actingAs($moussa)->test(PageConversation::class, ['conversation' => $conversation])->assertSeeText('Awa Traoré')->set('body', 'Merci, quand pouvons-nous commencer ?')->call('send');
    Livewire::actingAs($awa)->test(Espace::class)->assertSeeText('1 message non lu')->assertSeeText('Moussa Koné');

    // Un savoir n'a ni état « résolu » ni remerciement : aucune ligne « Remercier ».
    Livewire::actingAs($awa)->test(Espace::class)->assertDontSeeText('Remercier');
    expect(User::count())->toBe(2);
});
