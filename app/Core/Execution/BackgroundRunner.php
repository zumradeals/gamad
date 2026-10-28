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

            return $this->due() ? $this->run(self::MAX_JOBS, self::DURATION) : 0;
        } catch (Throwable $error) {
            // Le traitement de fond ne doit jamais faire échouer une requête déjà servie.
            report($error);

            return 0;
        }
    }

    /** Traite les tâches disponibles dans les limites données (commande d'exploitation, tests). */
    public function run(int $maxJobs, int $seconds): int
    {
        $lock = Cache::lock(self::LOCK, $seconds + 30);
        if (! $lock->get()) {
            return 0;
        }

        try {
            return $this->work($maxJobs, $seconds);
        } finally {
            $lock->release();
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
