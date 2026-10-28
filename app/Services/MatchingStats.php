<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Mesures réelles du Matching : des comptes et des durées sur les recherches et les pistes. Jamais de sujet, de texte, de nom ni
 * d'identifiant de personne. Source du panneau d'observation (Mon espace › Administration › Traitements du Core).
 */
class MatchingStats
{
    /** @return array{queued: int, running: int, stale: int, done: int, failed: int, cancelled: int, retried: int, produced: int, wait_p50_ms: ?int, wait_p95_ms: ?int, run_p50_ms: ?int, run_p95_ms: ?int} */
    public function searches(int $hours): array
    {
        $since = now()->subHours($hours);
        $row = DB::table('matching_searches')->where('requested_at', '>=', $since)->selectRaw(
            "count(*) filter (where state = 'queued') as queued,
             count(*) filter (where state = 'running' and heartbeat_at > ?) as running,
             count(*) filter (where state = 'running' and (heartbeat_at is null or heartbeat_at <= ?)) as stale,
             count(*) filter (where state = 'done') as done,
             count(*) filter (where state = 'failed') as failed,
             count(*) filter (where state = 'cancelled') as cancelled,
             count(*) filter (where attempts > 0) as retried,
             coalesce(sum(produced), 0) as produced,
             percentile_cont(0.5) within group (order by extract(epoch from (started_at - requested_at)) * 1000) filter (where started_at is not null) as wait50,
             percentile_cont(0.95) within group (order by extract(epoch from (started_at - requested_at)) * 1000) filter (where started_at is not null) as wait95,
             percentile_cont(0.5) within group (order by extract(epoch from (finished_at - started_at)) * 1000) filter (where state = 'done' and started_at is not null) as run50,
             percentile_cont(0.95) within group (order by extract(epoch from (finished_at - started_at)) * 1000) filter (where state = 'done' and started_at is not null) as run95",
            [now()->subMinutes(3), now()->subMinutes(3)]
        )->first();

        $ms = fn ($value) => $value === null ? null : (int) round($value);

        return [
            'queued' => (int) $row->queued, 'running' => (int) $row->running, 'stale' => (int) $row->stale, 'done' => (int) $row->done, 'failed' => (int) $row->failed,
            'cancelled' => (int) $row->cancelled, 'retried' => (int) $row->retried, 'produced' => (int) $row->produced,
            'wait_p50_ms' => $ms($row->wait50), 'wait_p95_ms' => $ms($row->wait95), 'run_p50_ms' => $ms($row->run50), 'run_p95_ms' => $ms($row->run95),
        ];
    }

    /** @return array{active: int, ignored: int, withdrawn: int, by_reason: array<string, int>} */
    public function suggestions(): array
    {
        $row = DB::table('match_suggestions')->selectRaw(
            'count(*) filter (where ignored_at is null and withdrawn_at is null) as active,
             count(*) filter (where ignored_at is not null) as ignored,
             count(*) filter (where withdrawn_at is not null) as withdrawn'
        )->first();

        return [
            'active' => (int) $row->active, 'ignored' => (int) $row->ignored, 'withdrawn' => (int) $row->withdrawn,
            'by_reason' => DB::table('match_suggestions')->whereNotNull('withdrawn_reason')->groupBy('withdrawn_reason')->orderByDesc(DB::raw('count(*)'))
                ->selectRaw('withdrawn_reason, count(*) as total')->pluck('total', 'withdrawn_reason')->map(fn ($n) => (int) $n)->all(),
        ];
    }
}
