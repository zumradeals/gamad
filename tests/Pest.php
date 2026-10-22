<?php

use App\Models\City;
use App\Models\Domain;
use App\Models\User;
use App\Services\ProfileEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

/**
 * Charge les fonctions de install.sh sans lancer l'installation, puis exécute un scénario.
 *
 * @return array{code: int, sortie: string}
 */
function scenarioInstall(string $scenario): array
{
    $script = base_path('deploy/install.sh');
    $resultat = Process::env(['GAMAD_SOURCE_ONLY' => '1'])
        ->run(['bash', '-c', "source '$script'\ntitre 'Étape d essai'\n$scenario"]);

    return ['code' => $resultat->exitCode(), 'sortie' => $resultat->output().$resultat->errorOutput()];
}

/**
 * Crée une personne vérifiée et enregistre son profil par le chemin réel (App\Services\ProfileEditor), comme l'écran
 * « Modifier mon profil ».
 *
 * @param  array<int, string>  $offered
 * @param  array<int, string>  $wanted
 * @param  array<int, string>  $domains  noms de domaines
 */
function person(string $name, array $offered = [], array $wanted = [], ?string $city = null, string $visibility = 'public', array $domains = [], ?string $bio = null, array $attributes = []): User
{
    $user = User::factory()->create(['name' => $name] + $attributes);

    $cityModel = $city === null ? null : City::query()->where('name', $city)->firstOrFail();

    (new ProfileEditor)->save($user, [
        'name' => $name,
        'bio' => $bio,
        'country_id' => $cityModel?->country_id,
        'city_id' => $cityModel?->id,
        'visibility' => $visibility,
        'offered' => $offered,
        'wanted' => $wanted,
        'domain_ids' => Domain::query()->whereIn('name', $domains)->pluck('id')->all(),
    ]);

    return $user->fresh();
}
