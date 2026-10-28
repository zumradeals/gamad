<?php

namespace App\Core\Execution;

use Closure;
use Throwable;

/**
 * Intergiciel de tâche : enregistre chaque tentative (durée, issue, résultats chiffrés) dans `execution_runs`, puis laisse l'erreur
 * remonter à Laravel, qui décide de la reprise (délais, nombre d'essais, table `failed_jobs`). La tâche peut exposer `runMetrics`
 * (comptes uniquement). L'enregistrement ne doit jamais faire échouer la tâche.
 */
class Instrumented
{
    public function handle(object $job, Closure $next): mixed
    {
        $start = hrtime(true);

        try {
            $result = $next($job);
        } catch (Throwable $error) {
            $this->record($job, 'failure', $start, $error);

            throw $error;
        }

        $this->record($job, 'success', $start);

        return $result;
    }

    private function record(object $job, string $outcome, int $start, ?Throwable $error = null): void
    {
        try {
            ExecutionRun::query()->create([
                'job' => class_basename($job),
                'attempt' => property_exists($job, 'job') && $job->job !== null ? max(1, (int) $job->job->attempts()) : 1,
                'outcome' => $outcome,
                'duration_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
                'metrics' => property_exists($job, 'runMetrics') && $job->runMetrics !== [] ? $job->runMetrics : null,
                'error_code' => $error === null ? null : mb_substr(class_basename($error), 0, 120),
                'ran_at' => now(),
            ]);
        } catch (Throwable $recording) {
            report($recording);
        }
    }
}
