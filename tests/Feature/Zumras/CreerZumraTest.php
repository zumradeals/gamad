<?php

use App\Enums\ZumraFormat;
use App\Enums\ZumraRole;
use App\Livewire\FormulaireZumra;
use App\Models\City;
use App\Models\Domain;
use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Services\Zumras;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->yao = User::factory()->create(['name' => 'Yao Kouassi']);
    $this->domaine = Domain::query()->where('name', 'Économie et Finances')->value('id');
    $this->ville = City::query()->where('name', 'Cocody')->value('id');
});

function donnees(array $surcharge = []): array
{
    return $surcharge + [
        'name' => 'Couture solidaire',
        'domain_id' => Domain::query()->where('name', 'Économie et Finances')->value('id'),
        'city_id' => City::query()->where('name', 'Cocody')->value('id'),
        'format' => 'hybrid',
        'sphere' => 'social',
        'mission' => 'Former des couturières et couturiers, et produire des uniformes scolaires à prix juste.',
    ];
}

function creer(User $qui, array $surcharge = []): Zumra
{
    return app(Zumras::class)->create($qui, donnees($surcharge));
}

// ─── Créer ───────────────────────────────────────────────────────────────────

it('crée une ZUMRA par l’écran, et son créateur en devient le fondateur et le premier membre', function () {
    Livewire::actingAs($this->yao)->test(FormulaireZumra::class)
        ->set('name', 'Couture solidaire')->set('domain_id', $this->domaine)->set('city_id', $this->ville)->set('format', 'hybrid')->set('sphere', 'social')
        ->set('mission', 'Former des couturières et couturiers.')->call('save')->assertHasNoErrors();

    $z = Zumra::query()->sole();
    expect($z->name)->toBe('Couture solidaire')->and($z->domain_id)->toBe($this->domaine)->and($z->city_id)->toBe($this->ville)
        ->and($z->format)->toBe(ZumraFormat::Hybrid)->and($z->mission)->toBe('Former des couturières et couturiers.');

    $m = ZumraMembership::query()->sole();
    expect($m->user_id)->toBe($this->yao->id)->and($m->zumra_id)->toBe($z->id)->and($m->role)->toBe(ZumraRole::Founder);
    expect($z->roleOf($this->yao))->toBe(ZumraRole::Founder)->and($z->roleOf(User::factory()->create()))->toBeNull()->and($z->roleOf(null))->toBeNull();
});

it('redirige vers le Monde de la ZUMRA avec un message', function () {
    Livewire::actingAs($this->yao)->test(FormulaireZumra::class)
        ->set('name', 'Couture solidaire')->set('domain_id', $this->domaine)->set('format', 'online')->set('sphere', 'social')->set('mission', 'Une mission.')->call('save')
        ->assertRedirect(route('zumras.show', Zumra::query()->sole()));

    $this->get(route('zumras.show', Zumra::query()->sole()))->assertSeeText('ZUMRA créée. Vous en êtes le fondateur.');
});

it('affiche l’écran de création avec ses champs, et dit que le nom et le domaine ne changeront plus', function () {
    $this->actingAs($this->yao)->get(route('zumras.create'))->assertOk()
        ->assertSeeText('Créer une ZUMRA')->assertSeeText('Domaine d’activité (un seul)')->assertSeeText('Où la ZUMRA se retrouve')->assertSeeText('Description courte')
        ->assertSeeText('Numérique')->assertSeeText('Physique')->assertSeeText('Hybride')->assertSeeText('Le nom et le domaine ne pourront plus changer')
        ->assertSeeText('Créer la ZUMRA')->assertSeeText('/ 500');
});

it('exige un nom de 3 à 80 caractères, espaces superflus retirés', function () {
    foreach (['', '  ', 'ab', str_repeat('é', 81)] as $mauvais) {
        expect(fn () => creer($this->yao, ['name' => $mauvais]))->toThrow(ValidationException::class);
    }

    expect(creer($this->yao, ['name' => '  Cercle   des   couturières  '])->name)->toBe('Cercle des couturières');
    expect(creer($this->yao, ['name' => str_repeat('é', 80)])->name)->toHaveLength(80);
});

it('refuse un nom déjà pris, sans tenir compte de la casse, des accents ni des espaces', function () {
    creer($this->yao, ['name' => 'Cercle d’étude et d’adoration']);

    foreach (['CERCLE D’ÉTUDE ET D’ADORATION', 'cercle d’etude et d’adoration', 'Cercle  d’étude   et d’adoration'] as $doublon) {
        expect(fn () => creer(User::factory()->create(), ['name' => $doublon]))->toThrow(ValidationException::class, 'déjà pris');
    }

    expect(Zumra::count())->toBe(1);
});

it('refuse aussi le doublon de nom au niveau de la base', function () {
    $z = creer($this->yao);

    expect(fn () => DB::transaction(fn () => DB::table('zumras')->insert([
        'id' => (string) Str::uuid(), 'name' => 'COUTURE SOLIDAIRE', 'name_key' => $z->name_key, 'search' => 'x',
        'domain_id' => $this->domaine, 'format' => 'online', 'mission' => 'x', 'created_at' => now(), 'updated_at' => now(),
    ])))->toThrow(UniqueConstraintViolationException::class);
});

it('exige un domaine actif de la liste, et un seul', function () {
    foreach ([null, '', 'pas-un-identifiant', (string) Str::uuid()] as $mauvais) {
        expect(fn () => creer($this->yao, ['domain_id' => $mauvais]))->toThrow(ValidationException::class);
    }

    $desactive = Domain::query()->where('name', 'Santé et Action sociale')->first() ?? Domain::query()->skip(3)->first();
    $desactive->update(['active' => false]);
    expect(fn () => creer($this->yao, ['domain_id' => $desactive->id]))->toThrow(ValidationException::class);

    // Un tableau de domaines n'est pas un domaine : une ZUMRA n'en a qu'un.
    expect(fn () => creer($this->yao, ['domain_id' => [$this->domaine, $desactive->id]]))->toThrow(ValidationException::class);
    expect(Zumra::count())->toBe(0);
});

it('accepte une ville facultative, active et de la liste', function () {
    expect(creer($this->yao, ['city_id' => null, 'name' => 'Sans ville'])->city_id)->toBeNull();

    foreach (['pas-un-identifiant', (string) Str::uuid()] as $mauvaise) {
        expect(fn () => creer($this->yao, ['city_id' => $mauvaise, 'name' => 'Ville '.Str::random(5)]))->toThrow(ValidationException::class);
    }

    City::query()->whereKey($this->ville)->update(['active' => false]);
    expect(fn () => creer($this->yao, ['name' => 'Ville désactivée']))->toThrow(ValidationException::class);
});

it('exige un format parmi en ligne, présentiel et hybride', function () {
    foreach ([null, '', 'mixte', 'ONLINE'] as $mauvais) {
        expect(fn () => creer($this->yao, ['format' => $mauvais, 'name' => 'Format '.Str::random(5)]))->toThrow(ValidationException::class);
    }

    foreach (['online', 'onsite', 'hybrid'] as $bon) {
        expect(creer($this->yao, ['format' => $bon, 'name' => 'Format '.$bon])->format->value)->toBe($bon);
    }
});

it('exige une mission non vide de 500 caractères au plus (D-114)', function () {
    foreach ([null, '', '   '] as $vide) {
        expect(fn () => creer($this->yao, ['mission' => $vide]))->toThrow(ValidationException::class);
    }

    expect(fn () => creer($this->yao, ['mission' => str_repeat('é', 501), 'name' => 'Trop longue']))->toThrow(ValidationException::class);
    expect(creer($this->yao, ['mission' => str_repeat('é', 500), 'name' => 'Pile 500'])->mission)->toHaveLength(500);
});

it('limite la création à 3 ZUMRA par jour et par personne (D-117)', function () {
    config(['gamad.limits.zumras_per_day' => 3]);

    foreach (['Une', 'Deux', 'Trois'] as $nom) {
        creer($this->yao, ['name' => "ZUMRA $nom"]);
    }

    expect(fn () => creer($this->yao, ['name' => 'ZUMRA Quatre']))->toThrow(ValidationException::class, 'limite');
    expect(Zumra::count())->toBe(3);

    // La limite est par personne.
    creer(User::factory()->create(), ['name' => 'ZUMRA Cinq']);
    expect(Zumra::count())->toBe(4);
});

it('réserve la création aux personnes connectées et vérifiées', function () {
    $this->get(route('zumras.create'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->unverified()->create())->get(route('zumras.create'))->assertRedirect();

    expect(fn () => creer(User::factory()->unverified()->create()))->toThrow(AuthorizationException::class);
    expect(Zumra::count())->toBe(0);
});

it('garantit en base un seul fondateur par ZUMRA, et un seul rôle par personne', function () {
    $z = creer($this->yao);
    $autre = User::factory()->create();

    expect(fn () => DB::transaction(fn () => ajouteMembre($z, $autre, ZumraRole::Founder)))->toThrow(UniqueConstraintViolationException::class);

    ajouteMembre($z, $autre, ZumraRole::Member);
    expect(fn () => DB::transaction(fn () => ajouteMembre($z, $autre, ZumraRole::Manager)))->toThrow(UniqueConstraintViolationException::class);

    // Plusieurs responsables sont possibles.
    ajouteMembre($z, User::factory()->create(), ZumraRole::Manager);
    ajouteMembre($z, User::factory()->create(), ZumraRole::Manager);
    expect(ZumraMembership::query()->where('role', 'manager')->count())->toBe(2);
});

it('refuse en base un rôle ou un format hors liste', function () {
    $z = creer($this->yao);

    expect(fn () => DB::transaction(fn () => DB::table('zumra_memberships')->insert(['id' => (string) Str::uuid(), 'zumra_id' => $z->id, 'user_id' => User::factory()->create()->id, 'role' => 'chef', 'created_at' => now(), 'updated_at' => now()])))
        ->toThrow(QueryException::class);
    expect(fn () => DB::transaction(fn () => DB::table('zumras')->where('id', $z->id)->update(['format' => 'mixte'])))->toThrow(QueryException::class);
});

it('affiche toutes les erreurs ensemble, chacune sous son champ, et ne crée rien', function () {
    Livewire::actingAs($this->yao)->test(FormulaireZumra::class)->call('save')
        ->assertHasErrors(['name', 'domain_id', 'format', 'mission'])
        ->assertSeeText('Le nom doit avoir entre 3 et 80 caractères.')->assertSeeText('Choisissez un domaine dans la liste.')
        ->assertSeeText('Choisissez où la ZUMRA se retrouve.')->assertSeeText('Écrivez la mission de la ZUMRA.');

    expect(Zumra::count())->toBe(0);
});

it('supprime la ZUMRA d’une personne qui supprime son compte quand elle est seule dedans', function () {
    $z = creer($this->yao);
    $autre = creer(User::factory()->create(), ['name' => 'Autre ZUMRA']);

    $this->yao->delete();

    expect(Zumra::query()->whereKey($z->id)->exists())->toBeFalse()->and(Zumra::query()->whereKey($autre->id)->exists())->toBeTrue();
    expect(ZumraMembership::query()->where('zumra_id', $z->id)->count())->toBe(0);
});

it('verrouille l’identifiant de la ZUMRA modifiée dans l’écran', function () {
    $z = creer($this->yao);
    $autre = creer(User::factory()->create(), ['name' => 'Autre ZUMRA']);

    $composant = Livewire::actingAs($this->yao)->test(FormulaireZumra::class, ['zumra' => $z]);
    expect(fn () => $composant->set('zumraId', $autre->id))->toThrow(CannotUpdateLockedPropertyException::class);
});
