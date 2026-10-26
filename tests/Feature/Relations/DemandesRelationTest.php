<?php

use App\Models\PersonConnection;
use App\Services\People;
use App\Services\Todo;

/** Les demandes de relation reçues apparaissent dans « À faire maintenant », sans pression. */
it('montre les demandes de relation reçues en une ligne discrète avec accès direct à Mes relations', function () {
    $moi = person('Awa Traoré');
    $a = person('Moussa Koné');
    $b = person('Nadia Test');
    expect(collect(app(Todo::class)->for($moi))->pluck('key'))->not->toContain('relations-recues');

    app(People::class)->request($a, $moi, 'Bonjour');
    $ligne = collect(app(Todo::class)->for($moi))->firstWhere('key', 'relations-recues');
    expect($ligne['detail'])->toBe('1 personne souhaite entrer en relation avec vous.')
        ->and($ligne['url'])->toBe(route('espace', ['rubrique' => 'relations']).'#mes-liens')
        ->and($ligne['action'])->toBe('Voir mes relations');

    app(People::class)->request($b, $moi, 'Bonjour aussi');
    $lignes = collect(app(Todo::class)->for($moi))->where('key', 'relations-recues');
    expect($lignes)->toHaveCount(1)->and($lignes->first()['detail'])->toBe('2 personnes souhaitent entrer en relation avec vous.');
});

it('n’affiche rien à l’expéditeur, et la ligne disparaît dès que les demandes sont traitées', function () {
    $moi = person('Awa Traoré');
    $a = person('Moussa Koné');
    app(People::class)->request($a, $moi, 'Bonjour');

    expect(collect(app(Todo::class)->for($a))->pluck('key'))->not->toContain('relations-recues');

    $revision = (int) PersonConnection::query()->forPerson($moi)->where('sender_id', $a->id)->value('revision');
    app(People::class)->decide($moi, $a, 'accept', $revision);

    expect(collect(app(Todo::class)->for($moi))->pluck('key'))->not->toContain('relations-recues');
});

it('ne compte pas les demandes de personnes bloquées', function () {
    $moi = person('Awa Traoré');
    $a = person('Moussa Koné');
    app(People::class)->request($a, $moi, 'Bonjour');
    app(People::class)->block($moi, $a, true);

    expect(collect(app(Todo::class)->for($moi))->pluck('key'))->not->toContain('relations-recues');
});

it('affiche cette ligne dans Mon espace', function () {
    $moi = person('Awa Traoré');
    app(People::class)->request(person('Moussa Koné'), $moi, 'Bonjour');

    $this->actingAs($moi)->get(route('espace'))->assertOk()->assertSeeText('À faire maintenant')->assertSeeText('Demandes de relation')
        ->assertSeeText('1 personne souhaite entrer en relation avec vous.')->assertSee('rubrique=relations', false);
});
