<?php

namespace App\Http\Middleware;

use App\Support\Experience;
use App\Support\ZumraModule;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Les anciens favoris mènent au parcours actif ; les pages de détail et leurs droits restent intacts. */
class SimplifyDiscovery
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Experience::initial()) {
            if ($request->routeIs('projets.index') || ($request->routeIs('zumras.index', 'decouvrir') && ! ZumraModule::accessibleTo($request->user()))) {
                return new RedirectResponse(route('personnes.index'));
            }

            if ($request->routeIs('besoins.index')) {
                return new RedirectResponse(route('fil', ['filtre' => 'besoins']));
            }
        }

        return $next($request);
    }
}
