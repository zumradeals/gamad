<?php

use App\Models\Skill;
use App\Support\Normalizer;
use Illuminate\Database\UniqueConstraintViolationException;

it('donne la même clé à « Comptabilité », « comptabilite » et « COMPTABILITÉ »', function () {
    expect(Normalizer::key('Comptabilité'))->toBe('comptabilite')
        ->and(Normalizer::key('comptabilite'))->toBe('comptabilite')
        ->and(Normalizer::key('COMPTABILITÉ'))->toBe('comptabilite')
        ->and(Normalizer::key("  Comptabilité \t "))->toBe('comptabilite');
});

it('ignore les espaces superflus et les caractères invisibles', function () {
    expect(Normalizer::key("  Gestion   de\u{00A0} petit \n commerce "))->toBe('gestion de petit commerce')
        ->and(Normalizer::key("Compta\u{200B}bilité"))->toBe('comptabilite')
        ->and(Normalizer::display('  Gestion   de petit  commerce '))->toBe('Gestion de petit commerce');
});

it('traite l’apostrophe typographique comme l’apostrophe droite', function () {
    expect(Normalizer::key('Droits de l’homme'))->toBe(Normalizer::key("Droits de l'homme"));
});

it('garde une clé non vide pour une écriture sans équivalent latin', function () {
    expect(Normalizer::key('日本語'))->toBe('日本語')
        ->and(Normalizer::key('日本語'))->not->toBe(Normalizer::key('中文'));
});

it('neutralise les caractères spéciaux d’une recherche LIKE', function () {
    expect(Normalizer::likeContains('100%_a\\b'))->toBe('%100\\%\\_a\\\\b%');
});

it('ne crée qu’une seule capacité pour « Comptabilité », « comptabilite » et « COMPTABILITÉ »', function () {
    $a = Skill::findOrCreateByName('Comptabilité');
    $b = Skill::findOrCreateByName('comptabilite');
    $c = Skill::findOrCreateByName('  COMPTABILITÉ ');

    expect($b->is($a))->toBeTrue()
        ->and($c->is($a))->toBeTrue()
        ->and(Skill::query()->where('normalized', 'comptabilite')->count())->toBe(1)
        ->and($a->name)->toBe('Comptabilité');
});

it('garde l’écriture de la première personne qui a créé la capacité', function () {
    Skill::findOrCreateByName('Tableur Excel');

    expect(Skill::findOrCreateByName('tableur excel')->name)->toBe('Tableur Excel');
});

it('refuse en base deux capacités de même clé', function () {
    Skill::findOrCreateByName('Couture');

    expect(fn () => Skill::query()->create(['name' => 'COUTURE']))->toThrow(UniqueConstraintViolationException::class);
});
