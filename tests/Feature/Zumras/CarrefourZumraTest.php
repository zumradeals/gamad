<?php

use App\Enums\ZumraRole;
use App\Livewire\CarrefourZumras;
use App\Models\City;
use App\Models\Domain;
use App\Models\User;
use App\Models\Zumra;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->yao = User::factory()->create(['name' => 'Yao Kouassi']);
    $this->agri = Domain::query()->where('name', 'like', 'Agriculture%')->value('id') ?? Domain::query()->skip(1)->value('id');
    $this->tech = Domain::query()->where('name', 'like', 'Technologie%')->value('id') ?? Domain::query()->skip(2)->value('id');
    $this->bouake = City::query()->where('name', 'Bouaké')->value('id');
    $this->abidjan = City::query()->where('name', 'Cocody')->value('id');
});

function carrefour(?User $qui = null)
{
    return $qui ? Livewire::actingAs($qui)->test(CarrefourZumras::class) : Livewire::test(CarrefourZumras::class);
}

it('est ouvert aux visiteurs sans compte, qui voient les ZUMRA', function () {
    zumra($this->yao, 'Couture solidaire');

    $this->get(route('zumras.index'))->assertOk()->assertSeeText('CARREFOUR')->assertSeeText('Couture solidaire')->assertSeeText('Entrer')->assertSeeText('Créer un compte');
});

it('affiche la carte d’une ZUMRA : nom, domaine · ville · format, mission, nombre de membres', function () {
    zumra($this->yao, 'Agri Bouaké', ['domain_id' => $this->agri, 'city_id' => $this->bouake, 'format' => 'onsite', 'mission' => 'Cultiver et transformer le manioc ensemble.']);

    $html = carrefour()->assertSeeText('Agri Bouaké')->assertSeeText('Présentiel')->assertSeeText('Bouaké')->assertSeeText('Cultiver et transformer le manioc ensemble.')->html();
    expect($html)->toContain(route('zumras.show', Zumra::query()->sole()));
});

it('classe les ZUMRA par ordre alphabétique, sans tenir compte des accents ni de la casse, jamais par activité', function () {
    foreach (['zèbre', 'Étoile', 'Alpha', 'béta'] as $nom) {
        zumra(User::factory()->create(), $nom);
    }
    // Une ZUMRA très « active » (beaucoup de membres) ne passe pas devant.
    $grosse = Zumra::query()->where('name', 'zèbre')->sole();
    foreach (range(1, 4) as $i) {
        ajouteMembre($grosse, User::factory()->create());
    }

    carrefour()->assertSeeTextInOrder(['Alpha', 'béta', 'Étoile', 'zèbre'])->assertDontSeeText('plus actives');
});

it('ne montre que des chiffres réels : le nombre de membres, ni projets, ni savoirs partagés, ni filtre « Reconnues »', function () {
    $z = zumra($this->yao, 'Couture solidaire');
    ajouteMembre($z, User::factory()->create());

    carrefour()->assertSeeText('2')->assertSeeText('membres')
        ->assertDontSeeText('projet')->assertDontSeeText('savoirs partagés')->assertDontSeeText('Reconnue');
});

it('ne compte pas, parmi les membres, une personne dont l’adresse n’est plus vérifiée', function () {
    $z = zumra($this->yao, 'Couture solidaire');
    $gone = User::factory()->create();
    ajouteMembre($z, $gone);
    $gone->forceFill(['email_verified_at' => null])->save();

    carrefour()->assertSeeText('1')->assertDontSeeText('2 membres');
});

it('cherche par nom ou par mission, sans tenir compte de la casse ni des accents', function () {
    zumra($this->yao, 'Couture solidaire', ['mission' => 'Former des couturières.']);
    zumra(User::factory()->create(), 'Agri Bouaké', ['mission' => 'Cultiver le manioc.']);

    carrefour()->set('query', 'SOLIDAIRE')->assertSeeText('Couture solidaire')->assertDontSeeText('Agri Bouaké');
    carrefour()->set('query', 'manioc')->assertSeeText('Agri Bouaké')->assertDontSeeText('Couture solidaire');
    carrefour()->set('query', 'couturieres')->assertSeeText('Couture solidaire')->assertDontSeeText('Agri Bouaké');
    carrefour()->set('query', 'boUAke')->assertSeeText('Agri Bouaké');
});

it('neutralise les caractères spéciaux de la recherche', function () {
    zumra($this->yao, 'Couture solidaire');

    carrefour()->set('query', '%')->assertSeeText('Aucune ZUMRA ne correspond');
    carrefour()->set('query', '_')->assertSeeText('Aucune ZUMRA ne correspond');
});

it('filtre par domaine et par ville, et ignore un identifiant qui n’en est pas un', function () {
    zumra($this->yao, 'Agri Bouaké', ['domain_id' => $this->agri, 'city_id' => $this->bouake]);
    zumra(User::factory()->create(), 'Tech Cocody', ['domain_id' => $this->tech, 'city_id' => $this->abidjan]);

    carrefour()->set('domain', $this->agri)->assertSeeText('Agri Bouaké')->assertDontSeeText('Tech Cocody');
    carrefour()->set('city', $this->abidjan)->assertSeeText('Tech Cocody')->assertDontSeeText('Agri Bouaké');
    carrefour()->set('domain', $this->agri)->set('city', $this->abidjan)->assertSeeText('Aucune ZUMRA ne correspond');
    carrefour()->set('domain', 'pas-un-identifiant')->set('city', "' or 1=1 --")->assertSeeText('Agri Bouaké')->assertSeeText('Tech Cocody');
});

it('efface la recherche en un geste', function () {
    zumra($this->yao, 'Couture solidaire');

    carrefour()->set('query', 'zzzz')->assertSeeText('Aucune ZUMRA ne correspond')->call('clearFilters')->assertSet('query', '')->assertSeeText('Couture solidaire');
});

it('pagine par dix', function () {
    foreach (range(1, 12) as $i) {
        zumra(User::factory()->create(), sprintf('ZUMRA numéro %02d', $i));
    }

    carrefour()->assertSeeText('ZUMRA numéro 10')->assertDontSeeText('ZUMRA numéro 11')->assertSeeText('Page 1 sur 2')
        ->call('nextPage')->assertSeeText('ZUMRA numéro 11')->assertDontSeeText('ZUMRA numéro 01');
});

it('montre « Mes ZUMRA » avec mon rôle, aux personnes connectées seulement', function () {
    zumra($this->yao, 'Couture solidaire');
    $autre = zumra(User::factory()->create(), 'Agri Bouaké');
    ajouteMembre($autre, $this->yao, ZumraRole::Manager);
    zumra(User::factory()->create(), 'Étrangère à Yao');

    carrefour($this->yao)->assertSeeText('Mes ZUMRA')->assertSeeText('Fondateur')->assertSeeText('Responsable')->assertSeeText('Créer une ZUMRA');

    app('auth')->forgetGuards();
    carrefour()->assertDontSeeText('Mes ZUMRA');
});

it('affiche les états vides : première ZUMRA à créer, recherche sans résultat, visiteur', function () {
    carrefour($this->yao)->assertSeeText('Aucune ZUMRA pour l’instant.')->assertSeeText('Soyez la première personne')->assertSee(route('zumras.create'), false);

    app('auth')->forgetGuards();
    carrefour()->assertSeeText('Créez un compte')->assertSee(route('register'), false);
});

it('place « ZUMRA » dans la navigation des personnes connectées, et mène au Carrefour', function () {
    $html = $this->actingAs($this->yao)->get(route('fil'))->assertOk()->getContent();

    // Une fois dans la barre du haut (ordinateur) et une fois dans la barre du bas (téléphone).
    expect(substr_count($html, 'href="'.route('zumras.index').'"'))->toBe(2);
    $this->get(route('zumras.index'))->assertOk()->assertSee('aria-current="page"', false);
});

it('donne à chaque ZUMRA une adresse qui n’en révèle que l’identifiant', function () {
    $z = zumra($this->yao);

    expect(route('zumras.show', $z))->toContain($z->id)->not->toContain('couture');
    $this->get('/zumra/'.Str::uuid())->assertNotFound();
    $this->get('/zumra/pas-un-identifiant')->assertNotFound();
});
