<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applique le limiteur « register » à la seule route d'inscription de Fortify, dont le groupe
 * de routes ne permet pas d'ajouter un middleware par route.
 */
class ThrottleRegistration
{
    public function __construct(private ThrottleRequests $throttle) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('POST') && $request->routeIs('register.store')) {
            return $this->throttle->handle($request, $next, 'register');
        }

        return $next($request);
    }
}
