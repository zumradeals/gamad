<?php

use App\Livewire\Fil;
use App\Livewire\GestureSheet;
use App\Livewire\PagePublication;
use App\Livewire\PublierPublication;
use App\Models\Gesture;
use App\Models\Publication;
use App\Models\User;
use Livewire\Livewire;

/**
 * Les deux parcours du Lot 2, pour la partie livrée en 2a : de la publication à l'acceptation, par les écrans et dans l'ordre
 * qu'une vraie personne suivrait. La conversation et le remerciement s'y ajoutent en 2b.
 */
it('parcours « J’ai besoin » → « Je peux aider » → acceptation → résolu', function () {
    $yao = person('Yao Kouassi', ['Gestion de projet'], city: 'Cocody');
    $awa = person('Awa Traoré', ['Informatique', 'Tableur Excel'], city: 'Yamoussoukro');

    // 1. Yao choisit « J'ai besoin » sur l'écran Agir, puis publie son besoin.
    $this->actingAs($yao)->get('/agir')->assertOk()->assertSee(route('agir.publier', 'besoin'), false);
    $publier = Livewire::actingAs($yao)->test(PublierPublication::class, ['slug' => 'besoin'])
        ->set('body', 'Je cherche quelqu’un qui sait installer un logiciel de caisse pour ma boutique à Cocody. Samedi matin si possible.')
        ->set('skills', ['Tableur Excel'])
        ->call('publish');
    $besoin = Publication::query()->where('author_id', $yao->id)->firstOrFail();
    $publier->assertRedirect(route('publications.show', $besoin));

    // 2. Awa la trouve dans le Fil et ouvre « Je peux aider ».
    $fil = Livewire::actingAs($awa)->test(Fil::class)->assertSeeText('logiciel de caisse')->assertSeeText('Je peux aider');
    Livewire::actingAs($awa)->test(GestureSheet::class)
        ->dispatch('open-gesture', id: $besoin->id)
        ->set('message', 'Je fais ce travail régulièrement. Je suis libre samedi matin.')
        ->call('send')->assertHasNoErrors()->assertDispatched('gesture-changed');

    // 3. Awa voit sa proposition envoyée ; Yao voit « 1 personne propose son aide ».
    Livewire::actingAs($awa)->test(Fil::class)->assertSeeText('Proposition envoyée')->assertSeeText('Retirer ma proposition')->assertDontSeeText('Je peux aider');
    Livewire::actingAs($yao)->test(Fil::class)->assertSeeText('1 personne propose son aide')->assertSeeText('Voir les réponses');

    // 4. Yao ouvre « Mon besoin », lit la proposition et l'accepte : le besoin passe « en cours ».
    $this->actingAs($yao)->get(route('publications.show', $besoin))->assertOk()
        ->assertSeeText('Mon besoin')->assertSeeText('Ouvert')->assertSeeText('Awa Traoré')->assertSeeText('Je fais ce travail régulièrement.');
    $geste = Gesture::query()->firstOrFail();
    Livewire::actingAs($yao)->test(PagePublication::class, ['publication' => $besoin])->call('accept', $geste->id)->assertSeeText('Acceptée')->assertSeeText('En cours');

    // 5. Awa voit que sa proposition est acceptée.
    Livewire::actingAs($awa)->test(Fil::class)->assertSeeText('Acceptée')->assertDontSeeText('Retirer ma proposition');

    // 6. Yao marque son besoin comme résolu : il n'accepte plus de réponse.
    Livewire::actingAs($yao)->test(PagePublication::class, ['publication' => $besoin])->call('resolve')->assertSeeText('Besoin marqué comme résolu.');
    $this->actingAs(User::factory()->create())->get(route('publications.show', $besoin))->assertSeeText('Ce besoin est résolu.')->assertDontSeeText('Je peux aider');

    expect($besoin->fresh()->need_state->value)->toBe('resolved');
});

it('parcours « Je partage un savoir » → « Je veux apprendre » → apprenant accepté', function () {
    $awa = person('Awa Traoré', ['Comptabilité'], city: 'Yamoussoukro');
    $moussa = person('Moussa Koné', [], ['Comptabilité'], city: 'Bouaké');

    // 1. Awa partage un savoir.
    Livewire::actingAs($awa)->test(PublierPublication::class, ['slug' => 'savoir'])
        ->set('body', 'Atelier ouvert à tous : tenir les comptes d’un petit commerce, d’abord sur un cahier, puis sur téléphone. Samedi à 10 h, en ligne.')
        ->set('skills', ['Comptabilité'])
        ->call('publish')->assertHasNoErrors();
    $savoir = Publication::query()->where('author_id', $awa->id)->firstOrFail();

    // 2. Moussa le trouve (filtre Savoirs) et répond « Je veux apprendre ».
    Livewire::actingAs($moussa)->test(Fil::class)->call('setFilter', 'savoirs')->assertSeeText('Atelier ouvert à tous')->assertSeeText('Je veux apprendre');
    Livewire::actingAs($moussa)->test(GestureSheet::class)->dispatch('open-gesture', id: $savoir->id)
        ->assertSeeText('Je veux apprendre')->call('send')->assertHasNoErrors();

    // 3. Awa voit « 1 personne veut apprendre », puis accepte l'apprenant.
    $this->actingAs($awa)->get(route('publications.show', $savoir))->assertOk()
        ->assertSeeText('Mon savoir partagé')->assertSeeText('1 personne veut apprendre')->assertSeeText('Moussa Koné')->assertSeeText('Bouaké');
    $geste = Gesture::query()->firstOrFail();
    Livewire::actingAs($awa)->test(PagePublication::class, ['publication' => $savoir])->call('accept', $geste->id)->assertSeeText('Acceptée');

    // 4. Moussa voit qu'il est accepté.
    Livewire::actingAs($moussa)->test(Fil::class)->assertSeeText('Acceptée');

    expect($geste->fresh()->state->value)->toBe('accepted')->and($savoir->fresh()->need_state)->toBeNull();
});
