<?php

use App\Enums\ZumraRole;
use App\Livewire\CarrefourZumras;
use App\Models\City;
use App\Models\Country;
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

    $this->get(route('zumras.index'))->assertOk()->assertSeeText('Des communautés qui font la différence')->assertSeeText('Couture solidaire')->assertSeeText('Voir la ZUMRA')->assertSeeText('Créer un compte');
});

it('affiche la carte d’une ZUMRA : nom, domaine · ville · format, mission, nombre de membres', function () {
    zumra($this->yao, 'Agri Bouaké', ['domain_id' => $this->agri, 'city_id' => $this->bouake, 'format' => 'onsite', 'mission' => 'Cultiver et transformer le manioc ensemble.']);

    $html = carrefour()->assertSeeText('Agri Bouaké')->assertSeeText('Physique')->assertSeeText('Bouaké')->assertSeeText('Cultiver et transformer le manioc ensemble.')->html();
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

    $page = carrefour()->set('sort', 'alphabetique')->assertDontSeeText('plus actives');
    preg_match_all('/<h3[^>]*>\s*<a[^>]*>([^<]+)</', $page->html(), $noms);
    expect($noms[1])->toBe(['Alpha', 'béta', 'Étoile', 'zèbre']);
});

it('ne montre que des chiffres réels : le nombre de membres, ni savoirs partagés', function () {
    $z = zumra($this->yao, 'Couture solidaire');
    ajouteMembre($z, User::factory()->create());

    carrefour()->assertSeeText('2')->assertSeeText('membres')
        ->assertDontSeeText('savoirs partagés')->assertDontSeeText('Reconnue par GAMAD');
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

it('pagine par douze', function () {
    foreach (range(1, 14) as $i) {
        zumra(User::factory()->create(), sprintf('ZUMRA numéro %02d', $i));
    }

    carrefour()->set('sort', 'alphabetique')->assertSeeText('ZUMRA numéro 12')->assertDontSeeText('ZUMRA numéro 13')->assertSeeText('Page 1 sur 2')
        ->call('nextPage')->assertSeeText('ZUMRA numéro 13')->assertDontSeeText('ZUMRA numéro 01');
});

it('montre « Mes ZUMRA » avec mon rôle, aux personnes connectées seulement', function () {
    zumra($this->yao, 'Couture solidaire');
    $autre = zumra(User::factory()->create(), 'Agri Bouaké');
    ajouteMembre($autre, $this->yao, ZumraRole::Manager);
    zumra(User::factory()->create(), 'Étrangère à Yao');

    carrefour($this->yao)->assertSeeText('Mes ZUMRA')->assertSeeText('Créer une ZUMRA')->assertSee(route('espace.zumras'), false);

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

    // Une fois par barre de navigation ; les accès d'exploration du Fil sont indépendants.
    preg_match_all('/<nav aria-label="Navigation principale".*?<\/nav>/s', $html, $navs);
    expect($navs[0])->toHaveCount(2);
    foreach ($navs[0] as $navigation) {
        expect(substr_count($navigation, 'href="'.route('zumras.index').'"'))->toBe(1);
    }
    $this->get(route('zumras.index'))->assertOk()->assertSee('aria-current="page"', false);
});

it('donne à chaque ZUMRA une adresse qui n’en révèle que l’identifiant', function () {
    $z = zumra($this->yao);

    expect(route('zumras.show', $z))->toContain($z->id)->not->toContain('couture');
    $this->get('/zumra/'.Str::uuid())->assertNotFound();
    $this->get('/zumra/pas-un-identifiant')->assertNotFound();
});

it('filtre par pays et réduit la liste des villes, puis efface la ville au changement de pays', function () {
    $ci = City::findOrFail($this->bouake)->country_id;
    $senegal = Country::query()->create(['name' => 'Pays de test']);
    $dakar = City::query()->create(['name' => 'Ville de test', 'country_id' => $senegal->id]);
    zumra($this->yao, 'Communauté ivoirienne', ['city_id' => $this->bouake]);
    zumra(User::factory()->create(), 'Communauté extérieure', ['city_id' => $dakar->id]);
    zumra(User::factory()->create(), 'Communauté en ligne', ['city_id' => null, 'format' => 'online']);

    carrefour()->set('country', $ci)->assertSeeText('Communauté ivoirienne')->assertDontSeeText('Communauté extérieure')->assertDontSeeText('Communauté en ligne')
        ->set('city', $this->bouake)->set('country', $senegal->id)->assertSet('city', '')
        ->assertSeeText('Communauté extérieure')->assertDontSeeText('Communauté ivoirienne')
        ->call('clearFilters')->assertSet('country', '')->assertSeeText('Communauté en ligne');
});

it('combine le format aux autres filtres et ignore les valeurs invalides', function () {
    zumra($this->yao, 'Apprendre en ligne', ['format' => 'online', 'city_id' => null]);
    zumra(User::factory()->create(), 'Apprendre sur place', ['format' => 'onsite', 'city_id' => $this->bouake]);

    carrefour()->set('format', 'online')->assertSeeText('Apprendre en ligne')->assertDontSeeText('Apprendre sur place')
        ->set('city', $this->bouake)->assertSeeText('Aucune ZUMRA ne correspond')
        ->call('clearFilters')->assertSet('format', '')->set('format', 'inconnu')->set('country', 'incorrect')
        ->assertSeeText('Apprendre en ligne')->assertSeeText('Apprendre sur place');
});

it('revient à la première page après un changement de pays ou de format', function () {
    foreach (range(1, 14) as $i) {
        zumra(User::factory()->create(), sprintf('Communauté %02d', $i), ['format' => 'onsite', 'city_id' => $this->bouake]);
    }
    carrefour()->call('nextPage')->set('format', 'onsite')->assertSeeText('Page 1 sur 2')
        ->call('nextPage')->set('country', City::findOrFail($this->bouake)->country_id)->assertSeeText('Page 1 sur 2');
});

it('conserve les territoires désactivés déjà sélectionnés dans une adresse', function () {
    $ville = City::findOrFail($this->bouake);
    zumra($this->yao, 'Communauté locale', ['city_id' => $ville->id]);
    $ville->country->update(['active' => false]);
    $ville->update(['active' => false]);

    Livewire::withQueryParams(['pays' => $ville->country_id, 'ville' => $ville->id])->test(CarrefourZumras::class)
        ->assertSeeText('Communauté locale')
        ->assertSeeText($ville->fullLabel())
        ->assertViewHas('countries', fn ($countries) => $countries->contains('id', $ville->country_id));
});
