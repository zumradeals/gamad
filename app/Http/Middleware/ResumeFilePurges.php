<?php

namespace App\Http\Middleware;

use App\Core\Files\FilePurger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Reprise des effacements de fichiers interrompus, sans dépendre d'un planificateur serveur (le dépôt ne garantit pas que
 * `schedule:run` tourne). Après l'envoi de la réponse, au plus une fois toutes les cinq minutes pour toute l'application, un petit
 * lot de fichiers en attente est repris. Aucune latence pour la personne ; sans fichier en attente, un seul test d'existence.
 * La commande planifiée `gamad:purger-fichiers-en-attente` fait le même travail quand un planificateur est présent.
 */
class ResumeFilePurges
{
    public const KEY = 'file-purges:resume';

    public const EVERY_SECONDS = 300;

    private const BATCH = 50;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            // `add` est atomique : un seul processus reprend à la fois, les autres passent leur tour.
            if (Cache::has(self::KEY) || ! Cache::add(self::KEY, true, self::EVERY_SECONDS)) {
                return;
            }

            $purger = app(FilePurger::class);

            if ($purger->pending()['retryable'] > 0) {
                $purger->drain(limit: self::BATCH);
            }
        } catch (Throwable $error) {
            // La reprise ne doit jamais faire échouer une requête déjà servie.
            report($error);
        }
    }
}
