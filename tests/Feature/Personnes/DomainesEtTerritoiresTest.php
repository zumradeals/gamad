<?php

use App\Exceptions\ImmutableRecordException;
use App\Livewire\ModifierProfil;
use App\Livewire\ParametresDomaines;
use App\Livewire\ParametresTerritoires;
use App\Models\AdminAction;
use App\Models\City;
use App\Models\Country;
use App\Models\Domain;
use App\Models\User;
use App\Services\ReferenceAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

function superAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

dataset('pages', ['/espace/parametres/domaines', '/espace/parametres/territoires']);

it('refuse les sections Domaines et Territoires aux visiteurs, membres et modérateurs', function (string $page) {
    $this->get($page)->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get($page)->assertForbidden();
    $this->actingAs(User::factory()->moderator()->create())->get($page)->assertForbidden();
    $this->actingAs(superAdmin())->get($page)->assertOk();
})->with('pages');

it('refuse aussi les actions elles-mêmes à un membre, même si la page était ouverte', function () {
    $membre = User::factory()->create();
    $domaine = Domain::query()->firstOrFail();

    Livewire::actingAs($membre)->test(ParametresDomaines::class)->assertForbidden();
    Livewire::actingAs($membre)->test(ParametresTerritoires::class)->assertForbidden();

    $admin = new ReferenceAdmin;
    expect(fn () => $admin->create($membre, 'domain', ['name' => 'Piratage']))->toThrow(AuthorizationException::class)
        ->and(fn () => $admin->rename($membre, $domaine, 'Piratage'))->toThrow(AuthorizationException::class)
        ->and(fn () => $admin->setActive(User::factory()->moderator()->create(), $domaine, false))->toThrow(AuthorizationException::class);

    expect(Domain::query()->where('name', 'Piratage')->exists())->toBeFalse()->and(AdminAction::query()->count())->toBe(0);
});

it('mène aux deux sections depuis Paramètres, pour le super administrateur', function () {
    $this->actingAs(superAdmin())->get('/espace/parametres')->assertOk()
        ->assertSee(route('espace.parametres.domaines'), false)
        ->assertSee(route('espace.parametres.territoires'), false);
});

it('ajoute un domaine, le met en dernier et l’inscrit au journal', function () {
    $admin = superAdmin();

    Livewire::actingAs($admin)->test(ParametresDomaines::class)
        ->set('newName', '  Énergie   et   Environnement ')
        ->call('add')
        ->assertHasNoErrors()
        ->assertSeeText('Domaine ajouté.')
        ->assertSet('newName', '');

    $domaine = Domain::query()->where('name', 'Énergie et Environnement')->firstOrFail();
    expect($domaine->active)->toBeTrue()
        ->and($domaine->sort_order)->toBe(180)
        ->and(Domain::query()->ordered()->get()->last()->is($domaine))->toBeTrue();

    $entree = AdminAction::query()->where('action', 'domain.created')->firstOrFail();
    expect($entree->actor_label)->toBe($admin->name)->and($entree->subject_id)->toBe($domaine->id)->and($entree->payload)->toBe(['nom' => 'Énergie et Environnement']);
});

it('refuse un domaine en double, sans tenir compte de la casse, des accents ni des espaces', function () {
    foreach (['religion', 'RELIGION', '  Religion  ', 'Sante et action sociale', 'sécurité'] as $nom) {
        Livewire::actingAs(superAdmin())->test(ParametresDomaines::class)->set('newName', $nom)->call('add')->assertHasErrors(['newName']);
    }

    expect(Domain::query()->count())->toBe(17)->and(AdminAction::query()->count())->toBe(0);
});

it('refuse un nom trop court ou trop long', function () {
    foreach (['a', '   ', str_repeat('x', 101)] as $nom) {
        Livewire::actingAs(superAdmin())->test(ParametresDomaines::class)->set('newName', $nom)->call('add')->assertHasErrors(['newName']);
    }
});

it('renomme un domaine : le journal garde l’ancien et le nouveau nom, et les personnes le voient renommé', function () {
    $admin = superAdmin();
    $awa = person('Awa', domains: ['Sécurité']);
    $domaine = Domain::query()->where('name', 'Sécurité')->firstOrFail();

    Livewire::actingAs($admin)->test(ParametresDomaines::class)
        ->call('startEdit', $domaine->id)
        ->assertSet('editName', 'Sécurité')
        ->set('editName', 'Sécurité et Protection')
        ->call('rename')
        ->assertHasNoErrors()
        ->assertSet('editingId', null)
        ->assertSeeText('Domaine renommé.');

    expect($domaine->fresh()->name)->toBe('Sécurité et Protection')
        ->and($awa->fresh()->profile->domains->pluck('name')->all())->toBe(['Sécurité et Protection']);

    $entree = AdminAction::query()->where('action', 'domain.renamed')->firstOrFail();
    expect($entree->payload)->toBe(['ancien' => 'Sécurité', 'nouveau' => 'Sécurité et Protection']);
});

it('ne journalise rien quand le nom ne change pas, et refuse un renommage vers un nom existant', function () {
    $domaine = Domain::query()->where('name', 'Religion')->firstOrFail();

    Livewire::actingAs(superAdmin())->test(ParametresDomaines::class)
        ->call('startEdit', $domaine->id)->call('rename')->assertSeeText('Aucune modification.')
        ->call('startEdit', $domaine->id)->set('editName', 'sécurité')->call('rename')->assertHasErrors(['editName']);

    expect(AdminAction::query()->count())->toBe(0)->and($domaine->fresh()->name)->toBe('Religion');
});

it('désactive puis réactive un domaine, avec une entrée au journal chaque fois', function () {
    $domaine = Domain::query()->where('name', 'Transport et Automobile')->firstOrFail();
    $page = Livewire::actingAs(superAdmin())->test(ParametresDomaines::class);

    $page->call('toggle', $domaine->id)->assertSeeText('Domaine désactivé.');
    expect($domaine->fresh()->active)->toBeFalse()->and(AdminAction::query()->where('action', 'domain.deactivated')->count())->toBe(1);

    $page->call('toggle', $domaine->id)->assertSeeText('Domaine réactivé.');
    expect($domaine->fresh()->active)->toBeTrue()->and(AdminAction::query()->where('action', 'domain.reactivated')->count())->toBe(1);
});

it('ne propose plus un domaine désactivé aux personnes, mais le garde à celles qui l’avaient choisi', function () {
    $awa = person('Awa', ['Couture'], domains: ['Transport et Automobile']);
    Domain::query()->where('name', 'Transport et Automobile')->update(['active' => false]);

    Livewire::actingAs(User::factory()->create())->test(ModifierProfil::class)->assertDontSeeText('Transport et Automobile');
    Livewire::actingAs($awa)->test(ModifierProfil::class)->assertSeeText('Transport et Automobile');
    $this->get(route('personnes.show', $awa->profile->public_id))->assertSeeText('Transport et Automobile');
});

it('ne permet jamais de supprimer un domaine, un pays ou une ville', function () {
    $page = Livewire::actingAs(superAdmin())->test(ParametresDomaines::class);
    $page->assertDontSeeText('Supprimer');
    Livewire::actingAs(superAdmin())->test(ParametresTerritoires::class)->assertDontSeeText('Supprimer');

    $awa = person('Awa', city: 'Bouaké', domains: ['Sécurité']);
    $ville = City::query()->where('name', 'Bouaké')->firstOrFail();

    expect(fn () => $ville->delete())->toThrow(QueryException::class)
        ->and(fn () => Domain::query()->where('name', 'Sécurité')->firstOrFail()->delete())->toThrow(QueryException::class)
        ->and(fn () => Country::query()->firstOrFail()->delete())->toThrow(QueryException::class);
});

it('ajoute un pays et une ville, renomme et désactive, avec un journal complet', function () {
    $admin = superAdmin();
    $page = Livewire::actingAs($admin)->test(ParametresTerritoires::class);

    $page->set('newCountry', 'Burkina Faso')->call('addCountry')->assertHasNoErrors()->assertSeeText('Pays ajouté.');
    $pays = Country::query()->where('name', 'Burkina Faso')->firstOrFail();

    $page->set('cityCountryId', $pays->id)->set('newCity', 'Bobo-Dioulasso')->call('addCity')->assertHasNoErrors()->assertSeeText('Ville ajoutée.');
    $ville = City::query()->where('name', 'Bobo-Dioulasso')->firstOrFail();
    expect($ville->country_id)->toBe($pays->id)->and($ville->label())->toBe('Bobo-Dioulasso');

    $page->call('startEdit', 'city', $ville->id)->set('editName', 'Bobo')->call('rename')->assertHasNoErrors();
    $page->call('toggle', 'city', $ville->id);
    $page->call('toggle', 'country', $pays->id);

    expect($ville->fresh()->name)->toBe('Bobo')->and($ville->fresh()->active)->toBeFalse()->and($pays->fresh()->active)->toBeFalse();

    expect(AdminAction::query()->orderBy('created_at')->pluck('action')->all())->toBe(['country.created', 'city.created', 'city.renamed', 'city.deactivated', 'country.deactivated']);
    expect(AdminAction::query()->where('action', 'city.renamed')->firstOrFail()->payload)->toBe(['ancien' => 'Bobo-Dioulasso', 'nouveau' => 'Bobo']);
});

it('refuse un pays ou une ville en double, mais accepte le même nom dans un autre pays', function () {
    $autre = Country::query()->create(['name' => 'Pays voisin']);
    $page = Livewire::actingAs(superAdmin())->test(ParametresTerritoires::class);

    $page->set('newCountry', 'cote d’ivoire')->call('addCountry')->assertHasErrors(['newCountry']);

    $ci = Country::query()->where('name', 'Côte d’Ivoire')->firstOrFail();
    $page->set('cityCountryId', $ci->id)->set('newCity', 'BOUAKE')->call('addCity')->assertHasErrors(['newCity']);

    $page->set('cityCountryId', $autre->id)->set('newCity', 'Bouaké')->call('addCity')->assertHasNoErrors();
    expect(City::query()->where('name', 'Bouaké')->count())->toBe(2);
});

it('distingue les communes d’un même district de la ville du même nom', function () {
    $ci = Country::query()->where('name', 'Côte d’Ivoire')->firstOrFail();
    $page = Livewire::actingAs(superAdmin())->test(ParametresTerritoires::class);

    $page->set('cityCountryId', $ci->id)->set('newCity', 'Cocody')->set('newCityArea', 'Abidjan')->call('addCity')->assertHasErrors(['newCity']);
    $page->set('newCity', 'Riviera')->set('newCityArea', 'Abidjan')->call('addCity')->assertHasNoErrors();

    expect(City::query()->where('name', 'Riviera')->firstOrFail()->label())->toBe('Abidjan, Riviera');
});

it('refuse une ville sans pays valide', function () {
    Livewire::actingAs(superAdmin())->test(ParametresTerritoires::class)
        ->set('cityCountryId', 'n-existe-pas')->set('newCity', 'Ville')->call('addCity')->assertHasErrors(['cityCountryId']);

    expect(City::query()->where('name', 'Ville')->exists())->toBeFalse();
});

it('propose les villes ajoutées dans l’écran du profil, et plus celles désactivées', function () {
    $pays = Country::query()->create(['name' => 'Burkina Faso']);
    $ville = City::query()->create(['country_id' => $pays->id, 'name' => 'Bobo-Dioulasso']);

    $editeur = Livewire::actingAs(User::factory()->create())->test(ModifierProfil::class);
    $editeur->assertSeeText('Burkina Faso')->set('country_id', $pays->id)->assertSeeText('Bobo-Dioulasso');

    $ville->update(['active' => false]);
    $editeur->call('$refresh')->assertDontSeeText('Bobo-Dioulasso');
});

it('inscrit chaque acte au journal sous le nom du super administrateur, et le journal reste non modifiable', function () {
    $admin = superAdmin();
    (new ReferenceAdmin)->create($admin, 'domain', ['name' => 'Nouveau domaine']);

    $entree = AdminAction::query()->firstOrFail();

    expect($entree->actor_id)->toBe($admin->id)
        ->and(fn () => $entree->update(['action' => 'autre']))->toThrow(ImmutableRecordException::class)
        ->and(fn () => $entree->delete())->toThrow(ImmutableRecordException::class);
});

it('annule le changement si le journal ne peut pas être écrit (même transaction)', function () {
    $admin = superAdmin();
    $domaine = Domain::query()->where('name', 'Religion')->firstOrFail();
    DB::statement('create or replace function refuse_journal() returns trigger as $$ begin raise exception \'journal indisponible\'; end; $$ language plpgsql');
    DB::statement('create trigger refuse_journal before insert on admin_actions for each row execute function refuse_journal()');

    try {
        expect(fn () => (new ReferenceAdmin)->rename($admin, $domaine, 'Spiritualité'))->toThrow(QueryException::class);
        expect($domaine->fresh()->name)->toBe('Religion');
    } finally {
        DB::statement('drop trigger refuse_journal on admin_actions');
    }
});

it('efface l’erreur affichée dès qu’une action suivante réussit', function () {
    $page = Livewire::actingAs(superAdmin())->test(ParametresDomaines::class);

    $page->set('newName', 'Religion')->call('add')->assertHasErrors(['newName'])
        ->set('newName', 'Un domaine inédit')->call('add')->assertHasNoErrors();
});

it('répond 404 à un identifiant falsifié dans les pages d’administration', function () {
    Livewire::actingAs(superAdmin())->test(ParametresDomaines::class)->call('startEdit', 'garbage')->assertNotFound();
    Livewire::actingAs(superAdmin())->test(ParametresDomaines::class)->call('toggle', "1' or '1'='1")->assertNotFound();
    Livewire::actingAs(superAdmin())->test(ParametresTerritoires::class)->call('startEdit', 'city', 'garbage')->assertNotFound();
    Livewire::actingAs(superAdmin())->test(ParametresTerritoires::class)->call('toggle', 'country', 'garbage')->assertNotFound();
    Livewire::actingAs(superAdmin())->test(ParametresTerritoires::class)->set('cityCountryId', 'garbage')->assertOk();
});
