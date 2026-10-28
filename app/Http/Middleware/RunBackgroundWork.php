<?php

namespace App\Http\Middleware;

use App\Core\Execution\BackgroundRunner;
use App\Core\Execution\Execution;
use Closure;
use Illuminate\Http\Request;
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
        app(BackgroundRunner::class)->opportunistic(now: (bool) $request->attributes->get(Execution::REQUESTED, false));
    }
}
