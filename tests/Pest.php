<?php

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
