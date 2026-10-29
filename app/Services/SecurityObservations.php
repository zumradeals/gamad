<?php

namespace App\Services;

use App\Core\Identity\SecurityAccessEvent;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SecurityObservations
{
    public function events(): Builder
    {
        return DB::table('security_access_events')->where('occurred_at', '>=', now()->subDays(max(1, (int) config('core_console.security.detail_days'))));
    }

    public function overview(int $hours): array
    {
        $hourly = DB::table('security_access_hours')->where('hour', '>=', now()->startOfHour()->subHours($hours - 1))
            ->where('hour', '>=', now()->startOfHour()->subDays(max(1, (int) config('core_console.security.aggregate_days'))))
            ->orderBy('hour')->get()->map(fn ($row) => (array) $row)->all();
        $totals = ['success' => 0, 'failure' => 0, 'limited' => 0, 'refused' => 0];
        foreach ($hourly as $row) {
            foreach ($totals as $key => $value) {
                $totals[$key] += (int) $row[$key];
            }
        }

        return [
            'hours' => $hourly, 'totals' => $totals,
            'latest' => $this->events()->max('occurred_at'),
            'targets' => $this->events()->where('occurred_at', '>=', now()->subHours($hours))->whereNotNull('target_key')->distinct()->count('target_key'),
            'sources' => $this->events()->where('occurred_at', '>=', now()->subHours($hours))->whereNotNull('source_key')->distinct()->count('source_key'),
            'recurrent_sources' => $this->events()->where('occurred_at', '>=', now()->subHours($hours))->whereNotNull('source_key')->groupBy('source_key')
                ->selectRaw('source_key, count(*) as total, min(id::text) as anchor')->orderByDesc('total')->limit(10)->get(),
            'recurrent_targets' => $this->events()->where('occurred_at', '>=', now()->subHours($hours))->whereNotNull('target_key')->groupBy('target_key')
                ->selectRaw('target_key, count(*) as total, bool_or(target_id is not null) as known, min(id::text) as anchor')->orderByDesc('total')->limit(10)->get(),
            'unknown_sources' => $this->events()->where('occurred_at', '>=', now()->subHours($hours))->whereNull('source_key')->count(),
            'recording_error' => cache()->get('security:recording-error'),
            'retention_error' => cache()->get('security:retention-error'),
        ];
    }

    /** Suspicions explained by observations; never a finding of guilt. SQL aggregates, bounded results. */
    public function incidents(int $hours = 24, bool $currentOnly = false): array
    {
        $minutes = max(1, (int) config('core_console.security.window_minutes'));
        $cutoff = now()->subMinutes($minutes);
        $since = $currentOnly ? $cutoff : now()->subHours($hours);
        $bucket = $currentOnly ? '1' : 'floor(extract(epoch from occurred_at) / '.($minutes * 60).')';
        $incidents = [];
        foreach (['target', 'source'] as $dimension) {
            $key = $dimension.'_key';
            $threshold = $dimension === 'target' ? 'account_failures' : 'source_failures';
            $q = $this->events()->where('occurred_at', '>=', $since)->whereNotNull($key)
                ->whereIn('outcome', ['failure', 'limited', 'refused'])
                ->groupBy($key)->groupByRaw($bucket)
                ->selectRaw("$key, min(id::text) as anchor, count(*) as attempts, count(distinct source_key) as sources, count(distinct target_key) as targets,
                    count(*) filter (where outcome = 'limited') as limited, bool_or(sensitive_target) as sensitive,
                    min(occurred_at) as first_at, max(occurred_at) as last_at")
                ->havingRaw('count(*) >= ? or count(distinct '.($dimension === 'target' ? 'source_key' : 'target_key').') >= ? or count(*) filter (where outcome = \'limited\') >= ?'.($dimension === 'target' ? ' or (bool_or(sensitive_target) and count(*) >= ?)' : ''),
                    array_merge([(int) config('core_console.security.'.$threshold), (int) config('core_console.security.'.($dimension === 'target' ? 'distributed_sources' : 'multiple_targets')), (int) config('core_console.security.excessive_limits')], $dimension === 'target' ? [(int) config('core_console.security.sensitive_failures')] : []))
                ->orderByDesc('last_at')->limit(20);
            foreach ($q->get() as $row) {
                $types = [];
                if ($dimension === 'target') {
                    if ($row->sources >= config('core_console.security.distributed_sources')) {
                        $types[] = 'Tentatives distribuées contre une adresse de connexion';
                    }
                    if ($row->sensitive && $row->attempts >= config('core_console.security.sensitive_failures')) {
                        $types[] = 'Compte sensible visé à répétition';
                    }
                    if ($row->attempts >= config('core_console.security.account_failures')) {
                        $types[] = 'Force brute suspectée contre une adresse de connexion';
                    }
                } else {
                    if ($row->targets >= config('core_console.security.multiple_targets')) {
                        $types[] = 'Identifiants multiples essayés depuis une même source apparente';
                    }
                    if ($row->attempts >= config('core_console.security.source_failures')) {
                        $types[] = 'Tentatives massives depuis une même source apparente';
                    }
                }
                if ($row->limited >= config('core_console.security.excessive_limits')) {
                    $types[] = 'Limitations excessives';
                }
                $incidents[] = ['engine' => 'Identity', 'title' => implode(' · ', $types), 'first_at' => $row->first_at, 'last_at' => $row->last_at,
                    'current' => $currentOnly, 'attempts' => (int) $row->attempts, 'sources' => (int) $row->sources, 'targets' => (int) $row->targets,
                    'anchor' => $row->anchor, 'dimension' => $dimension, 'certainty' => 'Rapprochement technique ; auteur physique non identifié'];
            }
        }
        // A success is suspicious only if enough failures preceded it in the rolling window, for that target.
        $scanSince = $since->copy()->subMinutes($minutes)->max(now()->subDays(max(1, (int) config('core_console.security.detail_days'))));
        $window = DB::table('security_access_events')->where('occurred_at', '>=', $scanSince)->whereNotNull('target_key')
            ->selectRaw("id, occurred_at, outcome, count(*) filter (where outcome in ('failure', 'limited', 'refused')) over (partition by target_key order by occurred_at range between interval '$minutes minutes' preceding and interval '0.000001 seconds' preceding) as prior_failures");
        $successes = DB::query()->fromSub($window, 'observed')->where('outcome', 'success')->where('occurred_at', '>=', $since)
            ->where('prior_failures', '>=', (int) config('core_console.security.account_failures'))->orderByDesc('occurred_at')->limit(20)->get(['id', 'occurred_at']);
        foreach ($successes as $row) {
            $incidents[] = ['engine' => 'Identity', 'title' => 'Connexion réussie après une série d’échecs suspects', 'first_at' => $row->occurred_at, 'last_at' => $row->occurred_at,
                'current' => $currentOnly, 'attempts' => null, 'sources' => null, 'targets' => 1, 'anchor' => $row->id, 'dimension' => 'target',
                'certainty' => 'Compte authentifié ; compromission non démontrée'];
        }

        return $incidents;
    }

    /** Identifying details never enter a public Livewire property or an unlogged view. */
    public function investigate(User $actor, string $id, string $dimension): array
    {
        Gate::forUser($actor)->authorize('super-admin');
        abort_unless(in_array($dimension, ['source', 'target'], true), 422);
        $anchor = SecurityAccessEvent::query()->where('occurred_at', '>=', now()->subDays(max(1, (int) config('core_console.security.detail_days'))))->findOrFail($id);
        $key = $dimension.'_key';
        $minutes = max(1, (int) config('core_console.security.window_minutes'));
        $query = SecurityAccessEvent::query()->where('occurred_at', '>=', now()->subDays(max(1, (int) config('core_console.security.detail_days'))))
            ->whereBetween('occurred_at', [$anchor->occurred_at->subMinutes($minutes), $anchor->occurred_at->addMinutes($minutes)]);
        if ($anchor->$key === null) {
            // An absent origin is not a common origin. Never correlate all unknown sources.
            $query->whereKey($anchor->id);
        } else {
            $query->where($key, $anchor->$key);
        }
        $rows = $query->orderByRaw('abs(extract(epoch from (occurred_at - ?::timestamptz)))', [$anchor->occurred_at->format('Y-m-d H:i:s.uP')])->orderBy('id')->limit(101)->get();
        AdminJournal::record($actor, 'console.security.investigate', subject: $anchor, payload: ['dimension' => $dimension, 'displayed' => min(100, $rows->count())]);
        $truncated = $rows->count() > 100;
        $rows = $rows->take(100)->sortBy('occurred_at')->values();

        return ['truncated' => $truncated, 'events' => $rows, 'dimension' => $dimension,
            'geolocation' => 'Non disponible : aucune source fiable configurée. Une IP ou un navigateur ne prouve pas l’identité physique.'];
    }
}
