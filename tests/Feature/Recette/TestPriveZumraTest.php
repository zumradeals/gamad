<?php

use App\Models\User;
use App\Services\Memberships;
use App\Services\Settings;
use App\Services\ZumraModeration;
use App\Support\ProjectsModule;
use App\Support\PubliciteModule;
use App\Support\ZumraModule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Configuration cible du test privé de ZUMRA (27 octobre 2026) : ZUMRA en test privé pour des testeurs désignés, Projets désactivé
 * explicitement, diffusion payante de GAMAD Publicité fermée. Les réglages se changent depuis Paramètres, jamais par un déploiement.
 */
it('garde fermés par défaut ZUMRA et la diffusion payante, et Projets suit ZUMRA : un déploiement n’ouvre rien', function () {
    $personne = person('Awa Traoré');
    $admin = User::factory()->superAdmin()->create();

    expect(app(Settings::class)->get('zumra_module'))->toBe('off')
        ->and(app(Settings::class)->get('projets_module'))->toBe('inherit')
        ->and(app(Settings::class)->get('publicite_diffusion'))->toBe('closed')
        ->and(ZumraModule::accessibleTo($personne))->toBeFalse()
        ->and(ZumraModule::accessibleTo($admin))->toBeFalse()
        ->and(PubliciteModule::diffusionOpen())->toBeFalse();
});

it('ouvre ZUMRA aux seuls testeurs, garde Projets fermé pour tous et la diffusion payante fermée', function () {
    $testeur = person('Salimata Ouattara');
    $autre = person('Ibrahim Traoré');
    $admin = User::factory()->superAdmin()->create();
    app(Settings::class)->update(['zumra_module' => 'test', 'projets_module' => 'off', 'zumra_testers' => $testeur->email], $admin);

    expect(ZumraModule::accessibleTo($testeur))->toBeTrue()
        ->and(ZumraModule::accessibleTo($admin))->toBeTrue()
        ->and(ZumraModule::accessibleTo($autre))->toBeFalse()
        ->and(ZumraModule::accessibleTo(null))->toBeFalse()
        ->and(ProjectsModule::accessibleTo($testeur))->toBeFalse()
        ->and(ProjectsModule::accessibleTo($admin))->toBeFalse()
        ->and(PubliciteModule::diffusionOpen())->toBeFalse();

    $zumra = zumra($testeur);
    $this->actingAs($testeur)->get(route('zumras.show', $zumra))->assertOk();
    $this->actingAs($autre)->get(route('zumras.show', $zumra))->assertNotFound();
    $this->get(route('zumras.show', $zumra))->assertNotFound();
    expect($this->actingAs($autre)->get(route('zumras.index'))->status())->not->toBe(200);
    $this->actingAs($testeur)->get(route('espace.projets'))->assertNotFound();
});

it('ne laisse pas une personne qui gère une ZUMRA la suspendre elle-même', function () {
    $fondatrice = person('Fondatrice');
    $moderateur = User::factory()->moderator()->create();
    c1ModuleZumra('public');
    $zumra = zumra($fondatrice);
    admis($zumra, $moderateur);
    app(Memberships::class)->promote($fondatrice, $zumra, ligne($zumra, $moderateur));

    expect(fn () => app(ZumraModeration::class)->suspend($moderateur, $zumra, 'Motif.'))->toThrow(ValidationException::class);
    expect($zumra->fresh()->isSuspended())->toBeFalse();
});

it('ne confie la fondation qu’à une personne dont l’adresse e-mail est vérifiée', function () {
    c1ModuleZumra('public');
    $fondatrice = person('Fondatrice');
    $zumra = zumra($fondatrice);
    $nonVerifiee = User::factory()->unverified()->create();
    ajouteMembre($zumra, $nonVerifiee);

    expect(fn () => app(Memberships::class)->transferFoundation($fondatrice, $zumra, ligne($zumra, $nonVerifiee)))->toThrow(AuthorizationException::class);
    expect($zumra->fresh()->roleOf($fondatrice)->value)->toBe('founder');
});
