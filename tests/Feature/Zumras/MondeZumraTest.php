<?php

use App\Enums\ZumraRole;
use App\Livewire\FormulaireZumra;
use App\Livewire\MondeZumra;
use App\Models\City;
use App\Models\Domain;
use App\Models\User;
use App\Services\Zumras;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

beforeEach(function () {
    $this->yao = person('Yao Kouassi', ['Couture'], city: 'Cocody');
    $this->awa = person('Awa Traoré', ['Informatique']);
    $this->zumra = zumra($this->yao, 'Couture solidaire', ['city_id' => City::query()->where('name', 'Cocody')->value('id'), 'format' => 'hybrid']);
});

// ─── Le Monde d'une ZUMRA ────────────────────────────────────────────────────

it('montre l’en-tête à un visiteur sans compte : nom, domaine · ville · format, état, mission, chiffres réels', function () {
    $this->get(route('zumras.show', $this->zumra))->assertOk()
        ->assertSeeText('Couture solidaire')->assertSeeText('Économie et Finances')->assertSeeText('Cocody')->assertSeeText('Hybride')
        ->assertSeeText('Active')->assertSeeText('Former des couturières et couturiers')->assertSeeText('membre')->assertSeeText('création')
        ->assertSeeText('Carrefour ZUMRA');
});

it('ne montre pas aux visiteurs la liste des membres : seulement une invitation à se connecter (D-112)', function () {
    $html = $this->get(route('zumras.show', $this->zumra))->assertOk()
        ->assertSeeText('Les membres d’une ZUMRA sont visibles des personnes inscrites.')->assertSee(route('login'), false)->getContent();

    expect($html)->not->toContain('Yao Kouassi')->not->toContain($this->yao->profile->public_id);
});

it('montre la liste des membres aux personnes connectées et vérifiées, le fondateur en tête avec son rôle', function () {
    ajouteMembre($this->zumra, $this->awa);
    ajouteMembre($this->zumra, person('Moussa Koné', []), ZumraRole::Manager);

    $html = $this->actingAs(User::factory()->create())->get(route('zumras.show', $this->zumra))->assertOk()
        ->assertSeeTextInOrder(['Yao Kouassi', 'Fondateur', 'Moussa Koné', 'Responsable', 'Awa Traoré'])->getContent();

    expect($html)->toContain(route('personnes.show', $this->yao->profile->public_id))->toContain(route('personnes.show', $this->awa->profile->public_id));
});

it('ne montre pas la liste des membres à une personne dont l’adresse n’est pas vérifiée', function () {
    Livewire::actingAs(User::factory()->unverified()->create())->test(MondeZumra::class, ['zumra' => $this->zumra])
        ->assertSeeText('Couture solidaire')->assertSeeText('visibles des personnes inscrites')->assertDontSeeText('Yao Kouassi');
});

it('ne montre pas, et ne compte pas, un membre dont l’adresse n’est plus vérifiée', function () {
    $membre = person('Chantal Disparue', []);
    ajouteMembre($this->zumra, $membre);
    $membre->forceFill(['email_verified_at' => null])->save();

    Livewire::actingAs($this->awa)->test(MondeZumra::class, ['zumra' => $this->zumra])->assertDontSeeText('Chantal Disparue')->assertDontSee('2 membres');
});

it('respecte la visibilité du profil : le nom d’un membre est un lien vers son profil pour qui peut le voir', function () {
    $discret = person('Auteur Discret', [], visibility: 'members');
    ajouteMembre($this->zumra, $discret);

    $html = $this->actingAs($this->awa)->get(route('zumras.show', $this->zumra))->getContent();
    expect($html)->toContain(route('personnes.show', $discret->profile->public_id));
});

it('masque ce qui n’existe pas encore : Suivre, Projets, Formation, Fil, Discussion, Reconnue', function () {
    $this->actingAs($this->awa)->get(route('zumras.show', $this->zumra))->assertOk()
        ->assertDontSeeText('Suivre')->assertDontSeeText('Projet')->assertDontSeeText('Formation')
        ->assertDontSeeText('Discussion')->assertDontSeeText('Reconnue')->assertDontSeeText('Savoirs partagés');
});

it('montre « Modifier la ZUMRA » au fondateur et aux responsables seulement', function () {
    $responsable = User::factory()->create();
    ajouteMembre($this->zumra, $responsable, ZumraRole::Manager);
    $membre = User::factory()->create();
    ajouteMembre($this->zumra, $membre);

    foreach ([$this->yao, $responsable] as $qui) {
        $this->actingAs($qui)->get(route('zumras.show', $this->zumra))->assertSeeText('Modifier la ZUMRA')->assertSee(route('zumras.edit', $this->zumra), false);
    }
    foreach ([$membre, $this->awa] as $qui) {
        $this->actingAs($qui)->get(route('zumras.show', $this->zumra))->assertDontSeeText('Modifier la ZUMRA');
    }
    app('auth')->forgetGuards();
    $this->get(route('zumras.show', $this->zumra))->assertDontSeeText('Modifier la ZUMRA');
});

it('verrouille la ZUMRA du composant', function () {
    $autre = zumra(User::factory()->create(), 'Autre ZUMRA');

    $composant = Livewire::test(MondeZumra::class, ['zumra' => $this->zumra]);
    expect(fn () => $composant->set('zumraId', $autre->id))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('échappe le HTML dans le nom et la mission, et ne rend aucun lien cliquable', function () {
    $z = zumra(User::factory()->create(), 'ZUMRA <b>piège</b>', ['mission' => '<script>alert(1)</script> https://exemple.org/piege']);

    $html = $this->get(route('zumras.show', $z))->getContent();

    expect($html)->not->toContain('<script>alert(1)')->not->toContain('<b>piège</b>')->not->toContain('href="https://exemple.org')
        ->and($html)->toContain('&lt;script&gt;');
});

// ─── Modifier ────────────────────────────────────────────────────────────────

it('laisse le fondateur modifier la mission, la ville et le format', function () {
    Livewire::actingAs($this->yao)->test(FormulaireZumra::class, ['zumra' => $this->zumra])
        ->assertSet('name', 'Couture solidaire')->assertSeeText('Le nom et le domaine ne changent pas après la création')
        ->set('mission', 'Nouvelle mission.')->set('city_id', '')->set('format', 'online')->call('save')->assertHasNoErrors()
        ->assertRedirect(route('zumras.show', $this->zumra));

    $z = $this->zumra->fresh();
    expect($z->mission)->toBe('Nouvelle mission.')->and($z->city_id)->toBeNull()->and($z->format->value)->toBe('online');
});

it('laisse aussi un responsable modifier', function () {
    $responsable = User::factory()->create();
    ajouteMembre($this->zumra, $responsable, ZumraRole::Manager);

    app(Zumras::class)->update($responsable, $this->zumra, ['mission' => 'Par un responsable.', 'format' => 'onsite']);

    expect($this->zumra->fresh()->mission)->toBe('Par un responsable.');
});

it('ne change jamais le nom ni le domaine, même si la requête les contient (D-114)', function () {
    $autreDomaine = Domain::query()->where('id', '!=', $this->zumra->domain_id)->value('id');

    app(Zumras::class)->update($this->yao, $this->zumra, ['name' => 'Nom volé', 'domain_id' => $autreDomaine, 'mission' => 'Mission.', 'format' => 'online']);

    $z = $this->zumra->fresh();
    expect($z->name)->toBe('Couture solidaire')->and($z->domain_id)->not->toBe($autreDomaine)->and($z->name_key)->toBe('couture solidaire');
});

it('refuse la modification aux membres et aux autres, par l’écran comme par le service', function () {
    $membre = User::factory()->create();
    ajouteMembre($this->zumra, $membre);

    foreach ([$membre, $this->awa] as $qui) {
        $this->actingAs($qui)->get(route('zumras.edit', $this->zumra))->assertNotFound();
        expect(fn () => app(Zumras::class)->update($qui, $this->zumra, ['mission' => 'Piraté', 'format' => 'online']))->toThrow(AuthorizationException::class);
    }

    expect($this->zumra->fresh()->mission)->not->toBe('Piraté');
});

it('demande de se connecter à un visiteur qui ouvre l’écran de modification', function () {
    $this->get(route('zumras.edit', $this->zumra))->assertRedirect(route('login'));
});

it('exige une mission valide à la modification, et garde une ville désactivée depuis', function () {
    foreach ([null, '', str_repeat('é', 501)] as $mauvaise) {
        expect(fn () => app(Zumras::class)->update($this->yao, $this->zumra, ['mission' => $mauvaise, 'format' => 'online']))->toThrow(ValidationException::class);
    }
    expect(fn () => app(Zumras::class)->update($this->yao, $this->zumra, ['mission' => 'Ok', 'format' => 'nope']))->toThrow(ValidationException::class);

    // La ville de la ZUMRA a été désactivée depuis : elle reste acceptée pour cette ZUMRA, pas pour une autre.
    City::query()->whereKey($this->zumra->city_id)->update(['active' => false]);
    app(Zumras::class)->update($this->yao, $this->zumra, ['mission' => 'Ok', 'format' => 'online', 'city_id' => $this->zumra->city_id]);
    expect($this->zumra->fresh()->city_id)->not->toBeNull();
    expect(fn () => app(Zumras::class)->update($this->yao, $this->zumra, ['mission' => 'Ok', 'format' => 'online', 'city_id' => (string) Str::uuid()]))->toThrow(ValidationException::class);
});
