<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Domain;
use Database\Seeders\DomainSeeder;
use Database\Seeders\TerritorySeeder;

it('fournit les 17 domaines d’action, dans l’ordre de PRODUIT.md §7', function () {
    $names = Domain::query()->ordered()->pluck('name')->all();

    expect($names)->toHaveCount(17)
        ->and($names)->toBe(DomainSeeder::NAMES)
        ->and($names[0])->toBe('Santé et Action sociale')
        ->and($names[16])->toBe('Artisanat')
        ->and(Domain::query()->where('active', false)->count())->toBe(0);
});

it('lit les domaines de PRODUIT.md : le seeder et la documentation ne se contredisent pas', function () {
    $produit = file_get_contents(dirname(__DIR__, 3).'/docs/PRODUIT.md');
    $section = explode('## 7.', $produit)[1];

    foreach (DomainSeeder::NAMES as $name) {
        expect(str_replace('’', "'", $section))->toContain(str_replace('’', "'", $name));
    }
});

it('fournit la Côte d’Ivoire, ses 31 chefs-lieux de région, ses 2 districts autonomes et les 13 communes d’Abidjan', function () {
    $country = Country::query()->where('name', 'Côte d’Ivoire')->firstOrFail();

    expect(Country::query()->count())->toBe(1)
        ->and(TerritorySeeder::REGION_CAPITALS)->toHaveCount(31)
        ->and(TerritorySeeder::AUTONOMOUS_DISTRICTS)->toBe(['Abidjan', 'Yamoussoukro'])
        ->and(TerritorySeeder::ABIDJAN_COMMUNES)->toHaveCount(13)
        ->and($country->cities()->count())->toBe(31 + 2 + 13)
        ->and($country->cities()->where('active', false)->count())->toBe(0);

    expect(City::query()->where('name', 'Cocody')->firstOrFail()->label())->toBe('Abidjan, Cocody')
        ->and(City::query()->where('name', 'Bouaké')->firstOrFail()->label())->toBe('Bouaké')
        ->and(City::query()->where('name', 'Yamoussoukro')->exists())->toBeTrue();
});

it('ne crée aucun doublon, même sans tenir compte de la casse ni des accents', function () {
    $keys = City::query()->pluck('normalized');

    expect($keys->unique()->count())->toBe($keys->count());
});

it('ne remplit les listes qu’une fois : relancer les seeders ne recrée rien de ce que le super administrateur a changé', function () {
    Domain::query()->where('name', 'Religion')->firstOrFail()->update(['name' => 'Spiritualité', 'active' => false]);
    City::query()->where('name', 'Bouaké')->firstOrFail()->update(['active' => false]);

    (new DomainSeeder)->run();
    (new TerritorySeeder)->run();

    expect(Domain::query()->count())->toBe(17)
        ->and(Domain::query()->where('name', 'Religion')->exists())->toBeFalse()
        ->and(City::query()->count())->toBe(46)
        ->and(City::query()->where('name', 'Bouaké')->value('active'))->toBeFalse();
});

it('cite sa source dans le seeder des villes et ne contient aucune donnée d’exemple', function () {
    $code = file_get_contents(dirname(__DIR__, 3).'/database/seeders/TerritorySeeder.php');

    expect($code)->toContain('SOURCES')->toContain('2011-263');
});
