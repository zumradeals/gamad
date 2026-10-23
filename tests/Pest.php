<?php

use App\Enums\PublicationType;
use App\Models\City;
use App\Models\Domain;
use App\Models\Gesture;
use App\Models\Publication;
use App\Models\User;
use App\Services\Gestures;
use App\Services\ProfileEditor;
use App\Services\Publications;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        $this->withoutVite();
        // Les limites de débit contre le spam (D-086) ne gênent pas les tests ordinaires ; les tests qui les vérifient les abaissent.
        config([
            'gamad.limits.publications_per_hour' => 10000, 'gamad.limits.publications_per_day' => 10000,
            'gamad.limits.gestures_per_hour' => 10000, 'gamad.limits.gestures_per_day' => 10000,
            'gamad.limits.messages_per_minute' => 10000, 'gamad.limits.messages_per_day' => 10000,
        ]);
    })
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

/** Publie par le chemin réel (App\Services\Publications), comme l'écran « Publier ». */
function publication(User $author, PublicationType $type = PublicationType::Need, array $data = []): Publication
{
    return app(Publications::class)->publish($author, $type, $data + ['body' => 'Un texte de publication.']);
}

/** Envoie un geste par le chemin réel (App\Services\Gestures). */
function geste(User $helper, Publication $publication, ?string $mot = null): Gesture
{
    return app(Gestures::class)->propose($helper, $publication, $mot);
}
