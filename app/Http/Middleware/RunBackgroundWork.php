<?php

namespace App\Http\Middleware;

use App\Core\Execution\BackgroundRunner;
use App\Core\Execution\Execution;
use App\Core\Identity\SecurityRetention;
use App\Services\AdminJournal;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fait avancer les tâches du Core après l'envoi de la réponse, dans les limites de BackgroundRunner (durée, volume, concurrence,
 * fréquence). Une requête qui vient de confier une tâche la lance tout de suite ; les autres vérifient au plus toutes les 5 secondes.
 */
class RunBackgroundWork
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $intervention = $request->attributes->get('gamad.console.runner');
        if ($intervention !== null) {
            try {
                $done = app(BackgroundRunner::class)->run(BackgroundRunner::MAX_JOBS, BackgroundRunner::DURATION, 'console');
                AdminJournal::record($request->user(), 'console.runner.result', payload: ['request' => $intervention, 'processed' => $done, 'result' => 'passage terminé ; voir le journal du lanceur']);
            } catch (\Throwable) {
                AdminJournal::record($request->user(), 'console.runner.result', payload: ['request' => $intervention, 'result' => 'échec du passage']);
            }
        } else {
            app(BackgroundRunner::class)->opportunistic(now: (bool) $request->attributes->get(Execution::REQUESTED, false));
        }
        // The application also works without cron. Bound each cleanup and continue on the next visit.
        if (Cache::add('security:retention-pass', true, 60)) {
            try {
                app(SecurityRetention::class)->purge();
                cache()->forget('security:retention-error');
            } catch (\Throwable) {
                cache()->put('security:retention-error', now()->toIso8601String(), now()->addDay());
            }
        }
    }
}
