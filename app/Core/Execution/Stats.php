<?php

namespace App\Core\Execution;

use Illuminate\Support\Facades\DB;

/**
 * Mesures réelles de l'exécution, lues dans la file de Laravel (`jobs`, `failed_jobs`) et le journal `execution_runs`. Des comptes et
 * des durées : jamais de sujet, de contenu ni de personne. Source de la première vue d'observation du Core.
 */
class Stats
{
    /** @return array{due: int, waiting: int, reserved: int, oldest_due_seconds: ?int} */
    public function queue(): array
    {
        $table = config('queue.connections.database.table', 'jobs');
        $now = now()->getTimestamp();
        $stale = now()->subSeconds(BackgroundRunner::retryAfter())->getTimestamp();
        $row = DB::table($table)->where('queue', BackgroundRunner::QUEUE)->selectRaw(
            'count(*) filter (where available_at <= ? and (reserved_at is null or reserved_at <= ?)) as due,
             count(*) filter (where available_at > ? and reserved_at is null) as waiting,
             count(*) filter (where reserved_at is not null and reserved_at > ?) as reserved,
             min(available_at) filter (where available_at <= ? and (reserved_at is null or reserved_at <= ?)) as oldest',
            [$now, $stale, $now, $stale, $now, $stale]
        )->first();

        return [
            'due' => (int) $row->due, 'waiting' => (int) $row->waiting, 'reserved' => (int) $row->reserved,
            'oldest_due_seconds' => $row->oldest === null ? null : max(0, $now - (int) $row->oldest),
        ];
    }

    /** @return array{total: int, recent: list<array{job: string, failed_at: string, error: string}>} */
    public function failed(int $limit = 5): array
    {
        return [
            'total' => DB::table('failed_jobs')->count(),
            // Seule la classe de l'erreur est affichée : le message peut contenir des données.
            'recent' => DB::table('failed_jobs')->orderByDesc('failed_at')->limit($limit)->get(['payload', 'exception', 'failed_at'])->map(fn ($row) => [
                'job' => class_basename((string) (json_decode($row->payload, true)['displayName'] ?? 'inconnue')),
                'failed_at' => (string) $row->failed_at,
                'error' => class_basename((string) strtok((string) $row->exception, ':')),
            ])->all(),
        ];
    }

    /** @return list<array{job: string, runs: int, success: int, failure: int, retries: int, p50_ms: ?int, p95_ms: ?int, metrics: array<string, int>}> */
    public function runs(int $hours): array
    {
        $rows = DB::table('execution_runs')->where('ran_at', '>=', now()->subHours($hours))->groupBy('job')->orderBy('job')->selectRaw(
            "job, count(*) as runs, count(*) filter (where outcome = 'success') as success, count(*) filter (where outcome = 'failure') as failure,
             count(*) filter (where attempt > 1) as retries,
             percentile_cont(0.5) within group (order by duration_ms) as p50, percentile_cont(0.95) within group (order by duration_ms) as p95"
        )->get();

        return $rows->map(fn ($row) => [
            'job' => $row->job, 'runs' => (int) $row->runs, 'success' => (int) $row->success, 'failure' => (int) $row->failure, 'retries' => (int) $row->retries,
            'p50_ms' => $row->p50 === null ? null : (int) round($row->p50), 'p95_ms' => $row->p95 === null ? null : (int) round($row->p95),
            'metrics' => $this->metrics($row->job, $hours),
        ])->all();
    }

    /** @return array<string, int> Somme, par nom, des résultats chiffrés déclarés par la tâche. */
    private function metrics(string $job, int $hours): array
    {
        return DB::table('execution_runs')->where('job', $job)->where('outcome', 'success')->where('ran_at', '>=', now()->subHours($hours))->whereNotNull('metrics')
            ->get('metrics')->reduce(function (array $sum, $row) {
                foreach (json_decode($row->metrics, true) as $name => $value) {
                    if (is_int($value)) {
                        $sum[$name] = ($sum[$name] ?? 0) + $value;
                    }
                }

                return $sum;
            }, []);
    }
}
