<?php

namespace App\Core\Execution;

use Illuminate\Queue\Worker;
use Illuminate\Queue\WorkerOptions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Exécution des tâches en attente SANS worker permanent, en réutilisant la file de Laravel (table `jobs` : persistance, réservation,
 * reprise des tâches interrompues, délais croissants, table `failed_jobs`). Chaque passage est borné :
 *
 *  - durée     : DURATION secondes au plus, aucune nouvelle tâche au-delà ;
 *  - volume    : MAX_JOBS tâches au plus par passage ;
 *  - concurrence : un seul passage à la fois pour toute l'application (verrou atomique) ; un autre processus passe son tour ;
 *  - fréquence : un passage « opportuniste » au plus toutes les POLL secondes, hors demande immédiate de la requête qui vient
 *    d'ajouter une tâche.
 *
 * Aucune promesse de délai : si personne ne visite le site, rien ne s'exécute avant la prochaine visite ou la commande
 * `gamad:traiter` (qu'un minuteur peut appeler). L'état réel d'une tâche est toujours consultable (Stats).
 */
class BackgroundRunner
{
    public const CONNECTION = 'database';

    public const QUEUE = 'default';

    public const DURATION = 8;

    public const MAX_JOBS = 12;

    public const POLL = 5;

    private static bool $working = false;

    private static float $deadline = 0.0;

    private const LOCK = 'execution:runner';

    private const POLL_KEY = 'execution:poll';

    /**
     * Laravel ne consigne les échecs définitifs dans `failed_jobs` que dans la commande `queue:work`, pas dans le Worker : pendant un
     * passage de ce lanceur, c'est lui qui le fait (AppServiceProvider relie l'événement ; jamais de doublon avec `queue:work`).
     */
    public static function working(): bool
    {
        return self::$working;
    }

    /**
     * Temps qu'une tâche peut encore prendre dans le passage en cours : une tâche longue le lit pour rendre la main à temps (elle reprendra
     * au curseur). Hors passage (commande, tests), `$default`. Le plafond du passage est coopératif : aucune tâche n'est interrompue de force.
     */
    public static function remaining(float $default): float
    {
        return self::$working ? max(0.5, min($default, self::$deadline - microtime(true))) : $default;
    }

    /** Une tâche d'une exécution précédente restée « réservée » (processus interrompu) redevient disponible après ce délai. */
    public static function retryAfter(): int
    {
        return (int) config('queue.connections.database.retry_after', 90);
    }

    /** Passage opportuniste, après l'envoi de la réponse. @return int Nombre de tâches traitées. */
    public function opportunistic(bool $now = false): int
    {
        try {
            if (! $now && ! Cache::add(self::POLL_KEY, true, self::POLL)) {
                return 0;
            }

            return $this->run(self::MAX_JOBS, self::DURATION, 'web');
        } catch (Throwable $error) {
            // Le traitement de fond ne doit jamais faire échouer une requête déjà servie.
            report($error);

            return 0;
        }
    }

    /** Traite les tâches disponibles dans les limites données (commande d'exploitation, tests). */
    public function run(int $maxJobs, int $seconds, string $origin = 'command'): int
    {
        $origin = in_array($origin, ['web', 'console', 'command'], true) ? $origin : 'command';
        $maxJobs = max(1, $maxJobs);
        $seconds = max(1, $seconds);
        $start = hrtime(true);
        $lock = Cache::lock(self::LOCK, $seconds + 30);
        $acquired = $lock->get();
        $done = 0;
        $outcome = 'success';
        $errorCode = null;
        try {
            if ($acquired) {
                $done = $this->work($maxJobs, $seconds);
            }

            return $done;
        } catch (Throwable $error) {
            $outcome = 'failure';
            $errorCode = class_basename($error);
            throw $error;
        } finally {
            if ($acquired) {
                $lock->release();
            }
            try {
                ExecutionRun::query()->create([
                    'job' => 'Runner:'.$origin, 'attempt' => 1, 'outcome' => $outcome,
                    'duration_ms' => (int) round((hrtime(true) - $start) / 1_000_000),
                    'metrics' => ['processed' => $done, 'contended' => $acquired ? 0 : 1, 'idle' => $acquired && $done === 0 && $outcome === 'success' ? 1 : 0],
                    'error_code' => $errorCode, 'ran_at' => now(),
                ]);
            } catch (Throwable $recording) {
                report($recording);
            }
        }
    }

    /** Y a-t-il une tâche à exécuter maintenant (disponible, ou réservée depuis trop longtemps) ? */
    public function due(): bool
    {
        return DB::table(config('queue.connections.database.table', 'jobs'))
            ->where('queue', self::QUEUE)
            ->where('available_at', '<=', now()->getTimestamp())
            ->where(fn ($q) => $q->whereNull('reserved_at')->orWhere('reserved_at', '<=', now()->subSeconds(self::retryAfter())->getTimestamp()))
            ->exists();
    }

    private function work(int $maxJobs, int $seconds): int
    {
        // Garde-fou pour un processus web (FPM) ; en ligne de commande, aucune limite n'est posée sur le processus appelant.
        PHP_SAPI === 'cli' || @set_time_limit($seconds + 20);
        self::$working = true;
        $deadline = microtime(true) + $seconds;
        self::$deadline = $deadline;
        $options = new WorkerOptions(name: 'opportuniste', backoff: 0, memory: 256, timeout: $seconds + 15, sleep: 0, maxTries: 5, stopWhenEmpty: true);
        /** @var Worker $worker */
        $worker = app('queue.worker');
        $done = 0;

        try {
            while ($done < $maxJobs && microtime(true) < $deadline && $this->due()) {
                $worker->runNextJob(self::CONNECTION, self::QUEUE, $options);
                $done++;
            }
        } finally {
            self::$working = false;
        }

        return $done;
    }
}
