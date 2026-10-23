<?php

namespace App\Http\Middleware;

use App\Services\Wallets;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Tant que le Président n'a pas ouvert les portefeuilles, leurs pages n'existent pas (404) : un module non construit est masqué. */
class EnsureWalletsEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app(Wallets::class)->enabled(), 404);

        return $next($request);
    }
}
