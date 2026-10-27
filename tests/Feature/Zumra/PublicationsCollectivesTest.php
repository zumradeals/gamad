<?php

use App\Enums\PublicationType;
use App\Livewire\MondeZumra;
use App\Livewire\PublierPublication;
use App\Models\Publication;
use App\Services\Publications;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/** D-214 : publier dans une ZUMRA quand le module est ouvert ; responsables : quatre types, membres : savoir et offre d'aide. */
beforeEach(function () {
    c1ModuleZumra('public');
    $this->fondatrice = person('Salimata Ouattara');
    $this->membre = person('Ibrahim Traoré');
    $this->etrangere = person('Nadia Kouassi');
    $this->zumra = zumra($this->fondatrice);
    admis($this->zumra, $this->membre);
});

it('laisse la fondatrice publier un appel, un besoin, un savoir et une offre au nom de la ZUMRA', function () {
    foreach ([PublicationType::ZumraCall, PublicationType::Need, PublicationType::Knowledge, PublicationType::Offer] as $type) {
        $publication = app(Publications::class)->publish($this->fondatrice, $type, ['body' => 'Un texte pour la ZUMRA.'], $this->zumra);
        expect($publication->zumra_id)->toBe($this->zumra->id);
    }
});

it('laisse un membre publier un savoir ou une offre d’aide, mais ni un besoin ni un appel', function () {
    foreach ([PublicationType::Knowledge, PublicationType::Offer] as $type) {
        $publication = app(Publications::class)->publish($this->membre, $type, ['body' => 'Je partage ceci.'], $this->zumra);
        expect($publication->author_id)->toBe($this->membre->id)->and($publication->zumra_id)->toBe($this->zumra->id);
    }

    foreach ([PublicationType::Need, PublicationType::ZumraCall] as $type) {
        expect(fn () => app(Publications::class)->publish($this->membre, $type, ['body' => 'Non.'], $this->zumra))->toThrow(AuthorizationException::class);
    }
});

it('refuse à une personne extérieure de publier dans la ZUMRA', function () {
    expect(fn () => app(Publications::class)->publish($this->etrangere, PublicationType::Knowledge, ['body' => 'Non.'], $this->zumra))
        ->toThrow(AuthorizationException::class);
    $this->actingAs($this->etrangere)->get(route('zumras.publier', [$this->zumra, 'savoir']))->assertNotFound();
});

it('garde les publications collectives fermées quand le module ZUMRA est fermé', function () {
    c1ModuleZumra('off');

    expect(fn () => app(Publications::class)->publish($this->fondatrice, PublicationType::Knowledge, ['body' => 'Non.'], $this->zumra))
        ->toThrow(ValidationException::class);
    $this->actingAs($this->fondatrice)->get(route('zumras.publier', [$this->zumra, 'savoir']))->assertNotFound();
});

it('publie par l’écran : le savoir d’un membre apparaît dans la Formation, le besoin lui est refusé', function () {
    Livewire::actingAs($this->membre)->test(PublierPublication::class, ['slug' => 'savoir', 'zumra' => $this->zumra])
        ->set('body', 'Comment tailler un patron de chemise.')->call('publish')->assertHasNoErrors();

    expect(Publication::query()->where('zumra_id', $this->zumra->id)->where('author_id', $this->membre->id)->count())->toBe(1);
    $this->actingAs($this->membre)->get(route('zumras.publier', [$this->zumra, 'besoin']))->assertNotFound();

    Livewire::actingAs($this->etrangere)->test(MondeZumra::class, ['zumra' => $this->zumra])->set('tab', 'formation')
        ->assertSee('Comment tailler un patron de chemise.');
});

it('propose les bons boutons de publication selon le rôle', function () {
    Livewire::actingAs($this->membre)->test(MondeZumra::class, ['zumra' => $this->zumra])->set('tab', 'fil')
        ->assertSee('Publier dans la ZUMRA')->assertSee('Je partage un savoir')->assertDontSee('Appel à rejoindre');
    Livewire::actingAs($this->fondatrice)->test(MondeZumra::class, ['zumra' => $this->zumra])->set('tab', 'fil')
        ->assertSee('Publier au nom de la ZUMRA')->assertSee('Appel à rejoindre');
    Livewire::actingAs($this->etrangere)->test(MondeZumra::class, ['zumra' => $this->zumra])->set('tab', 'fil')
        ->assertDontSee('Publier dans la ZUMRA')->assertDontSee('Publier au nom de la ZUMRA');
});

it('garde les publications de projet en pause', function () {
    $project = projet($this->fondatrice, $this->zumra);

    expect(fn () => app(Publications::class)->publish($this->fondatrice, PublicationType::ProjectUpdate, ['body' => 'Non.'], null, $project))
        ->toThrow(ValidationException::class);
});
