<?php

namespace App\Http\Middleware;

use App\Services\Wallets;
use App\Support\Experience;
use App\Support\WalletModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Tant que le Président n'a pas ouvert les portefeuilles, leurs pages n'existent pas (404) : un module non construit est masqué. */
class EnsureWalletsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(Wallets::class)->enabled(), 404);

        // Dans le parcours actif, le test privé se vérifie par personne (D-218) : un module fermé pour elle n'existe pas.
        abort_if(Experience::initial() && ! WalletModule::accessibleTo($request->user()), 404);

        return $next($request);
    }
}
