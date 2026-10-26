<?php

use App\Livewire\CarrefourPersonnes;
use App\Livewire\Espace;
use App\Livewire\Fil;
use App\Services\ComplementaryPeople;
use App\Services\People;
use Livewire\Livewire;

// Service unique des personnes complémentaires (audit A12, lot C7) : le Fil, le Carrefour Personnes et Mon espace s'y appuient.

beforeEach(function () {
    $this->moi = person('Moi Même', ['Comptabilité'], ['Menuiserie']);
    $this->awa = person('Awa Traoré', ['Menuiserie']);            // apporte ce que je veux apprendre
    $this->yao = person('Yao Kouassi', [], ['Comptabilité']);     // veut apprendre ce que j'apporte
    $this->zoe = person('Zoé Aucune', ['Cuisine'], ['Chant']);    // aucune capacité en commun
    $this->service = app(ComplementaryPeople::class);
});

it('propose les personnes complémentaires par ordre alphabétique, avec une raison, sans moi ni personne sans capacité en commun', function () {
    $liste = $this->service->suggestions($this->moi, $this->moi->profile, [], 5);

    expect($liste->pluck('user.name')->all())->toBe(['Awa Traoré', 'Yao Kouassi'])
        ->and($liste->firstWhere('user.name', 'Awa Traoré')->reason)->toBe('Peut vous apporter : Menuiserie')
        ->and($liste->firstWhere('user.name', 'Yao Kouassi')->reason)->toBe('Veut apprendre : Comptabilité');

    $explique = $this->service->suggestions($this->moi, $this->moi->profile, [], 5, explainLearning: true);
    expect($explique->firstWhere('user.name', 'Yao Kouassi')->reason)->toBe('Veut apprendre : Comptabilité, que vous pouvez apporter');
});

it('exclut les personnes en relation, suivies si demandé, bloquées et suspendues, et respecte la limite', function () {
    $people = app(People::class);
    $people->request($this->moi, $this->awa, 'Bonjour');
    expect($this->service->relatedUserIds($this->moi))->toBe([$this->awa->id]);
    $noms = fn (array $exclure) => $this->service->suggestions($this->moi, $this->moi->profile, $exclure, 5)->pluck('user.name')->all();

    expect($noms($this->service->relatedUserIds($this->moi)))->toBe(['Yao Kouassi']);

    $people->block($this->moi, $this->yao, true);
    expect($noms([]))->toBe(['Awa Traoré']);
    $people->block($this->moi, $this->yao, false);
    expect($noms([]))->toBe(['Awa Traoré', 'Yao Kouassi']);

    $this->yao->forceFill(['suspended_at' => now(), 'suspension_reason' => 'Test.'])->save();
    expect($noms([]))->toBe(['Awa Traoré']);
    $this->yao->forceFill(['suspended_at' => null, 'suspension_reason' => null])->save();

    expect($this->service->suggestions($this->moi, $this->moi->profile, [], 1))->toHaveCount(1);
});

it('ne suggère personne à qui n’a déclaré aucune capacité', function () {
    $vide = person('Sans Capacité');

    expect($this->service->suggestions($vide, $vide->profile, [], 5))->toBeEmpty();
});

it('donne les mêmes personnes au Fil, au Carrefour Personnes et à Mon espace', function () {
    $fil = Livewire::actingAs($this->moi)->test(Fil::class)->viewData('suggestions')->pluck('user.name')->all();
    $carrefour = Livewire::actingAs($this->moi)->test(CarrefourPersonnes::class)->viewData('suggestions')->pluck('user.name')->all();
    $espace = Livewire::actingAs($this->moi)->test(Espace::class)->viewData('suggestedPeople')->pluck('user.name')->all();

    expect($fil)->toBe(['Awa Traoré', 'Yao Kouassi'])->and($carrefour)->toBe($fil)->and($espace)->toBe($fil);
});
