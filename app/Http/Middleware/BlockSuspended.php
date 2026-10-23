<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Une personne suspendue (D-145) ne peut rien faire : toute page la ramène à l'écran qui lui dit pourquoi, hors la déconnexion et
 * les textes publics (charte, mentions légales). Posé sur tout le groupe web, donc aussi sur les requêtes Livewire.
 */
class BlockSuspended
{
    private const ALLOWED = ['suspendu', 'logout', 'charte', 'mentions'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isSuspended() || $request->routeIs(self::ALLOWED)) {
            return $next($request);
        }

        // Un composant déjà ouvert dans le navigateur n'a plus rien à recevoir : refus net, la page suivante expliquera.
        if ($request->hasHeader('X-Livewire') || $request->expectsJson()) {
            abort(403, 'Votre compte est suspendu.');
        }

        return redirect()->route('suspendu');
    }
}
