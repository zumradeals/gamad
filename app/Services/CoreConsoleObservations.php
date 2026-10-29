<?php

namespace App\Services;

use App\Core\Execution\Stats;
use App\Core\Files\FilePurger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class CoreConsoleObservations
{
    public function snapshot(int $hours): array
    {
        $observed = now()->toIso8601String();
        $health = [];
        $data = ['observed_at' => $observed, 'measurement_errors' => []];
        foreach (['Execution', 'Matching', 'Identity', 'Sauvegardes'] as $engine) {
            try {
                $health[$engine] = $this->measure($engine, $hours, $data);
            } catch (Throwable) {
                $health[$engine] = ['state' => 'inconnu', 'reason' => 'La mesure n’a pas pu être lue. Aucun état sain ne peut être déduit.', 'source' => $engine.' : mesure indisponible', 'freshness' => null];
                $data['measurement_errors'][] = $engine;
            }
        }
        $data['health'] = $health;
        $data['incidents'] = [];
        foreach ($health as $engine => $state) {
            if (in_array($state['state'], ['dégradé', 'en panne'], true)) {
                $data['incidents'][] = ['engine' => $engine, 'title' => $state['reason'], 'current' => true, 'last_at' => $state['freshness'] ?? $observed];
            }
        }
        foreach ($data['failed']['recent'] ?? [] as $failure) {
            $data['incidents'][] = ['engine' => 'Execution', 'title' => $failure['job'].' · '.$failure['error'].' (échec définitif enregistré)', 'current' => false, 'last_at' => $failure['failed_at']];
        }
        foreach ($data['failed_searches'] ?? [] as $failure) {
            $data['incidents'][] = ['engine' => 'Matching', 'title' => 'Recherche en échec définitif ; relance disponible sous vérifications', 'current' => false, 'last_at' => $failure->finished_at];
        }
        if (isset($data['backup']['dernier_echec']) && $data['backup']['dernier_echec']['depasse']) {
            $data['incidents'][] = ['engine' => 'Sauvegardes', 'title' => 'Ancien échec de copie suivi d’une réussite', 'current' => false, 'last_at' => $data['backup']['dernier_echec']['quand']->toIso8601String()];
        }
        $data['incidents'] = array_merge($data['incidents'], $data['security_incidents'] ?? []);

        return $data;
    }

    private function measure(string $engine, int $hours, array &$data): array
    {
        $freshness = now()->toIso8601String();
        $state = 'sain';
        $reason = '';
        if ($engine === 'Execution') {
            $stats = app(Stats::class);
            $data['queue'] = $queue = $stats->queue();
            $data['failed'] = $stats->failed();
            $data['runs'] = $stats->runs($hours);
            $data['runner'] = $runner = DB::table('execution_runs')->where('job', 'like', 'Runner:%')->orderByDesc('ran_at')->orderByDesc('id')->first();
            $data['last_console_run'] = DB::table('execution_runs')->where('job', 'Runner:console')->orderByDesc('ran_at')->orderByDesc('id')->first();
            $data['purges'] = app(FilePurger::class)->pending();
            $data['abandoned_purges'] = DB::table('file_purges')->where('attempts', '>=', FilePurger::MAX_ATTEMPTS)->orderByDesc('last_attempt_at')->limit(10)->get(['id', 'attempts', 'last_attempt_at']);
            $data['execution_hours'] = DB::table('execution_runs')->where('ran_at', '>=', now()->subHours($hours))->where('job', 'not like', 'Runner:%')
                ->groupByRaw("date_trunc('hour', ran_at)")->orderBy('hour')->selectRaw("date_trunc('hour', ran_at) as hour, count(*) as runs, count(*) filter (where outcome = 'failure') as failures, count(*) filter (where attempt > 1) as retries, sum(duration_ms) as duration_ms")->get();
            $recentFailure = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->exists();
            if (($queue['oldest_due_seconds'] ?? 0) > config('core_console.queue_degraded_seconds') || $recentFailure || $data['purges']['failed'] > 0) {
                $state = 'dégradé';
                $reason = implode(' · ', array_filter([
                    ($queue['oldest_due_seconds'] ?? 0) > config('core_console.queue_degraded_seconds') ? 'Travail réellement disponible au-delà du seuil d’attente' : null,
                    $recentFailure ? 'Échec définitif enregistré depuis moins de 24 h' : null,
                    $data['purges']['failed'] > 0 ? $data['purges']['failed'].' purge(s) abandonnée(s)' : null,
                ]));
            } elseif ($runner === null) {
                $state = 'inconnu';
                $reason = 'File mesurée, mais aucun passage réel du lanceur n’a encore été observé.';
            } elseif (Carbon::parse($runner->ran_at)->lt(now()->subMinutes(5))) {
                $state = 'inconnu';
                $reason = 'Dernier passage ancien ; sans travail disponible, cela ne prouve pas une panne.';
            } elseif ($runner->outcome === 'failure') {
                $state = 'dégradé';
                $reason = 'Le dernier passage du lanceur a échoué.';
            } else {
                $reason = $queue['due'] === 0 ? 'Aucun travail disponible ; un passage récent du lanceur est attesté.' : 'File disponible mesurée dans le seuil d’attente.';
            }
            $source = 'jobs, failed_jobs, execution_runs, file_purges (lecture actuelle)';
        } elseif ($engine === 'Matching') {
            $matching = app(MatchingStats::class);
            $data['searches'] = $matching->searches($hours);
            $data['suggestions'] = $matching->suggestions();
            $data['failed_searches'] = DB::table('matching_searches')->where('state', 'failed')->orderByDesc('finished_at')->limit(10)->get(['id', 'finished_at']);
            $data['cancelled_reasons'] = DB::table('matching_searches')->where('requested_at', '>=', now()->subHours($hours))->where('state', 'cancelled')->groupBy('cancel_reason')->selectRaw('cancel_reason, count(*) as total')->get();
            $data['matching_cost'] = DB::table('execution_runs')->where('job', 'RunMatchingSearch')->where('outcome', 'success')->where('ran_at', '>=', now()->subHours($hours))
                ->groupByRaw("date_trunc('hour', ran_at)")->orderBy('hour')->selectRaw("date_trunc('hour', ran_at) as hour, count(*) as slices, sum(duration_ms) as duration_ms,
                    sum(coalesce((metrics->>'examined')::bigint, 0)) as examined,
                    sum(duration_ms)::numeric / nullif(sum(coalesce((metrics->>'examined')::bigint, 0)), 0) as ms_per_candidate")->get();
            $latest = DB::table('execution_runs')->where('job', 'RunMatchingSearch')->max('ran_at');
            // Queued retries can legitimately wait for Laravel's backoff. Only a running search owes a heartbeat here.
            $stale = DB::table('matching_searches')->where('state', 'running')
                ->where(fn ($q) => $q->whereNull('heartbeat_at')->orWhere('heartbeat_at', '<', now()->subMinutes(3)))->exists();
            $recentFailure = DB::table('matching_searches')->where('state', 'failed')->where('finished_at', '>=', now()->subDay())->exists();
            if ($stale || $recentFailure) {
                $state = 'dégradé';
                $reason = $stale ? 'Recherche ouverte sans progression récente : reprise ou délai à examiner.' : 'Recherche en échec définitif depuis moins de 24 h.';
            } else {
                $state = $latest === null || Carbon::parse($latest)->lt(now()->subHours(config('core_console.evidence_fresh_hours'))) ? 'inconnu' : 'sain';
                $reason = $state === 'inconnu' ? 'Aucun calcul instrumenté récent reçu ; l’absence de recherche ne prouve pas une panne.' : 'États des recherches mesurés ; aucun retard ni échec sur la période. Dernier calcul : '.$latest;
            }
            $source = 'matching_searches, match_suggestions, execution_runs ; coût = durée totale instrumentée / candidats examinés';
        } elseif ($engine === 'Identity') {
            $security = app(SecurityObservations::class);
            $data['security'] = $security->overview($hours);
            $data['recent_security_events'] = $security->events()->orderByDesc('occurred_at')->limit(10)->get(['id', 'occurred_at', 'outcome', 'authenticated_id', 'target_id']);
            $data['current_security_incidents'] = $current = $security->incidents($hours, true);
            $data['security_incidents'] = collect(array_merge($current, $security->incidents($hours)))
                ->unique(fn ($incident) => $incident['dimension'].':'.$incident['anchor'].':'.$incident['title'])->values()->all();
            $expired = DB::table('security_access_events')->where('occurred_at', '<', now()->subDays(max(1, config('core_console.security.detail_days'))))->exists();
            if ($data['security']['recording_error'] || $data['security']['retention_error'] || $expired) {
                $state = 'dégradé';
                $reason = 'Collecte ou nettoyage des traces à vérifier ; les données expirées sont exclues des investigations.';
            } elseif ($current !== []) {
                $state = 'dégradé';
                $reason = count($current).' signal(aux) de sécurité actuel(s), à examiner sans présumer de culpabilité.';
            } else {
                $state = $data['security']['latest'] === null || Carbon::parse($data['security']['latest'])->lt(now()->subHours(config('core_console.evidence_fresh_hours'))) ? 'inconnu' : 'sain';
                $reason = $state === 'inconnu' ? 'Instrumentation active, aucun événement d’authentification récent reçu ; ce silence ne prouve pas une panne.' : 'Aucune concentration anormale dans la fenêtre de détection ; ce constat ne garantit pas l’absence d’attaque.';
            }
            $freshness = $data['security']['latest'];
            $source = 'Événements Laravel/Fortify · security_access_events et security_access_hours ; fenêtre '.config('core_console.security.window_minutes').' min';
        } else {
            $backup = app(BackupStorage::class);
            $data['backup'] = $status = $backup->status();
            $expected = config('core_console.backup_expected_hours');
            $source = 'BackupStorage · fichier d’état de la copie hors serveur';
            $freshness = $status['dernier_essai']?->toIso8601String();
            if (! $backup->configured()) {
                $state = 'non configuré';
                $reason = 'La copie hors serveur n’est pas configurée. La sauvegarde locale n’est pas instrumentée par cette source.';
            } elseif ($expected === null || ! is_numeric($expected) || $expected <= 0) {
                $state = 'inconnu';
                $reason = 'Cadence attendue non confirmée (GAMAD_BACKUP_EXPECTED_HOURS). Aucun seuil de panne arbitraire appliqué.';
            } elseif ($status['derniere_reussite'] === null) {
                $state = 'inconnu';
                $reason = 'Aucune réussite vérifiable reçue de la sauvegarde configurée.';
            } elseif ($status['derniere_reussite']->lt(now()->subHours((float) $expected + max(0, config('core_console.backup_grace_hours'))))) {
                $state = 'en panne';
                $reason = 'Dernière sauvegarde réussie au-delà de la cadence configurée et de sa tolérance.';
            } elseif ($status['dernier_echec'] !== null && ! $status['dernier_echec']['depasse']) {
                $state = 'dégradé';
                $reason = 'Dernier essai de copie échoué, sans réussite ultérieure.';
            } else {
                $reason = 'Copie hors serveur réussie dans la cadence configurée ; intégrité restaurable non démontrée par ce seul signal.';
            }
        }

        return compact('state', 'reason', 'source', 'freshness');
    }
}
