<?php

use App\Livewire\ModifierProfil;
use App\Livewire\ParametresTerritoires;
use App\Models\AdminAction;
use App\Models\City;
use App\Models\Country;
use App\Models\User;
use App\Services\AfricanGeography;
use Database\Seeders\TerritorySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/** D-217 : pays africains sélectionnables, pays facultatif à l'inscription, localité libre, recherche de lieux. */
function chargerCoteDIvoire(): Country
{
    (new TerritorySeeder)->run();

    return Country::query()->where('name', 'Côte d’Ivoire')->firstOrFail();
}

it('charge les pays africains en ajoutant seulement, sans toucher à la Côte d’Ivoire déjà relue', function () {
    $ci = chargerCoteDIvoire();
    $villesAvant = City::query()->where('country_id', $ci->id)->orderBy('id')->get(['id', 'name', 'area', 'normalized'])->toArray();
    $admin = User::factory()->superAdmin()->create();

    $plan = app(AfricanGeography::class)->plan();
    expect(Country::query()->count())->toBe(1)->and($plan['countries_new'])->toBe(53)->and($plan['places_new'])->toBeGreaterThan(5000);

    $resultat = app(AfricanGeography::class)->import($admin);

    expect($resultat['countries_new'])->toBe(53)->and(Country::query()->count())->toBe(54)
        ->and(Country::query()->whereNotNull('iso2')->count())->toBe(54)
        ->and($ci->fresh()->iso2)->toBe('CI')
        ->and(City::query()->where('country_id', $ci->id)->orderBy('id')->get(['id', 'name', 'area', 'normalized'])->toArray())->toBe($villesAvant)
        ->and(AdminAction::query()->where('action', 'country.africa_imported')->exists())->toBeTrue();
    expect(Country::query()->where('name', 'Nigeria')->exists())->toBeTrue()->and(City::query()->where('name', 'Lagos')->exists())->toBeTrue();
});

it('peut être relancé sans créer de doublon ni modifier ce qui existe', function () {
    chargerCoteDIvoire();
    $admin = User::factory()->superAdmin()->create();
    app(AfricanGeography::class)->import($admin);
    $villes = City::query()->count();
    $nigeria = Country::query()->where('iso2', 'NG')->firstOrFail();
    $nigeria->update(['name' => 'Nigéria (corrigé)']);

    $second = app(AfricanGeography::class)->import($admin);

    expect($second['countries_new'])->toBe(0)->and($second['places_new'])->toBe(0)->and(City::query()->count())->toBe($villes)
        ->and($nigeria->fresh()->name)->toBe('Nigéria (corrigé)')->and(Country::query()->count())->toBe(54);
});

it('reconnaît un pays déjà ajouté à la main au lieu d’en créer un second', function () {
    Country::query()->create(['name' => 'Sénégal']);

    $resultat = app(AfricanGeography::class)->import(User::factory()->superAdmin()->create());

    expect($resultat['countries_linked'])->toBeGreaterThanOrEqual(1)->and(Country::query()->where('name', 'Sénégal')->count())->toBe(1)
        ->and(Country::query()->where('name', 'Sénégal')->value('iso2'))->toBe('SN');
});

it('réserve l’import au super administrateur et l’inscrit au journal', function () {
    expect(fn () => app(AfricanGeography::class)->import(User::factory()->moderator()->create()))->toThrow(AuthorizationException::class);
    $this->actingAs(User::factory()->create())->get(route('espace.parametres.territoires'))->assertForbidden();

    Livewire::actingAs(User::factory()->superAdmin()->create())->test(ParametresTerritoires::class)
        ->call('previewAfrica')->assertSee('Lieux à ajouter')->call('importAfrica')->assertSee('Import terminé');
    expect(Country::query()->count())->toBe(54);
});

it('cherche les lieux par nom sans accents, au plus quinze, et seulement les actifs', function () {
    app(AfricanGeography::class)->import(User::factory()->superAdmin()->create());

    $reponse = $this->getJson(route('lieux.search', ['q' => 'lagos']))->assertOk();
    expect($reponse->json())->not->toBeEmpty()->and(count($reponse->json()))->toBeLessThanOrEqual(15)
        ->and(collect($reponse->json())->pluck('label')->first())->toContain('Lagos');

    $this->getJson(route('lieux.search', ['q' => 'l']))->assertOk()->assertExactJson([]);
    $this->getJson(route('lieux.search'))->assertOk()->assertExactJson([]);

    $lagos = City::query()->where('name', 'Lagos')->first();
    $lagos->update(['active' => false]);
    expect(collect($this->getJson(route('lieux.search', ['q' => 'lagos', 'pays' => $lagos->country_id]))->json())->pluck('id'))->not->toContain($lagos->id);
});

it('rend en entier la liste d’un pays à liste courte, pas celle d’un grand pays', function () {
    $ci = chargerCoteDIvoire();
    app(AfricanGeography::class)->import(User::factory()->superAdmin()->create());
    $nigeria = Country::query()->where('iso2', 'NG')->firstOrFail();

    expect(count($this->getJson(route('lieux.search', ['pays' => $ci->id]))->json()))->toBeGreaterThan(30)
        ->and($this->getJson(route('lieux.search', ['pays' => $nigeria->id]))->json())->toBe([]);
    // Un identifiant qui n'en est pas un est ignoré.
    $this->getJson(route('lieux.search', ['pays' => "' or 1=1 --", 'q' => 'ab']))->assertOk();
});

it('demande le pays à l’inscription sans jamais l’imposer', function () {
    $ci = chargerCoteDIvoire();
    $this->get(route('register'))->assertOk()->assertSee('Pays')->assertSee('Je le dirai plus tard')->assertSee('Côte d’Ivoire');

    $donnees = ['name' => 'Awa Koné', 'email' => 'awa@exemple.test', 'password' => 'motdepasse-solide-1', 'password_confirmation' => 'motdepasse-solide-1'];
    $this->post(route('register.store'), $donnees + ['country_id' => $ci->id])->assertRedirect();
    expect(User::query()->where('email', 'awa@exemple.test')->first()->profile->country_id)->toBe($ci->id);

    auth()->logout();
    $this->post(route('register.store'), ['email' => 'sans-pays@exemple.test'] + $donnees)->assertRedirect();
    expect(User::query()->where('email', 'sans-pays@exemple.test')->first()->profile?->country_id)->toBeNull();
});

it('refuse à l’inscription un pays inconnu, désactivé ou falsifié', function () {
    $ci = chargerCoteDIvoire();
    $donnees = ['name' => 'Awa Koné', 'email' => 'awa2@exemple.test', 'password' => 'motdepasse-solide-1', 'password_confirmation' => 'motdepasse-solide-1'];

    $this->from(route('register'))->post(route('register.store'), $donnees + ['country_id' => 'pas-un-identifiant'])->assertSessionHasErrors('country_id');
    $ci->update(['active' => false]);
    $this->from(route('register'))->post(route('register.store'), $donnees + ['country_id' => $ci->id])->assertSessionHasErrors('country_id');
    expect(User::query()->where('email', 'awa2@exemple.test')->exists())->toBeFalse();
});

it('enregistre une localité écrite librement, sans contact, et l’affiche sur le profil', function () {
    $ci = chargerCoteDIvoire();
    $utilisateur = User::factory()->create();
    $page = Livewire::actingAs($utilisateur)->test(ModifierProfil::class)->set('country_id', $ci->id);

    $page->set('locality', 'Quartier Sicogi 06 12 34 56 78')->call('save')->assertHasErrors(['locality']);
    $page->set('locality', 'contact@exemple.test')->call('save')->assertHasErrors(['locality']);
    $page->set('locality', 'Quartier Sicogi')->call('save')->assertHasNoErrors();

    expect($utilisateur->profile->fresh()->locality)->toBe('Quartier Sicogi');
    $this->get(route('personnes.show', $utilisateur->profile->fresh()))->assertSee('Quartier Sicogi');

    $sansPays = Livewire::actingAs(User::factory()->create())->test(ModifierProfil::class)->set('locality', 'Un village')->call('save');
    $sansPays->assertHasErrors(['locality']);
});

it('range les localités du matching en pastilles : cinq au plus, sans doublon ni lieu inventé', function () {
    $ci = chargerCoteDIvoire();
    $villes = City::query()->where('country_id', $ci->id)->limit(7)->pluck('id')->all();
    $page = Livewire::actingAs(User::factory()->create())->test(ModifierProfil::class);

    foreach ($villes as $id) {
        $page->set('zoneToAdd', $id);
    }
    $page->set('zoneToAdd', $villes[0])->set('zoneToAdd', 'pas-un-identifiant');
    expect($page->get('matching_preferences.zones'))->toHaveCount(5)->and($page->get('zoneToAdd'))->toBe('');

    $page->call('removeZone', $villes[0]);
    expect($page->get('matching_preferences.zones'))->toHaveCount(4)->not->toContain($villes[0]);
});
