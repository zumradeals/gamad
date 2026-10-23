<?php

namespace App\Http\Middleware;

use App\Support\Experience;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Les anciens favoris mènent au parcours actif ; les pages de détail et leurs droits restent intacts. */
class SimplifyDiscovery
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Experience::initial()) {
            if ($request->routeIs('decouvrir', 'zumras.index', 'projets.index')) {
                return redirect()->route('personnes.index');
            }

            if ($request->routeIs('besoins.index')) {
                return redirect()->route('fil', ['filtre' => 'besoins']);
            }
        }

        return $next($request);
    }
}
