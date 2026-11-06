<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\Http;

/**
 * Révocation de la session souveraine : aucun logout local ne peut
 * prétendre avoir révoqué un jeton si le Core n'a pas confirmé.
 */
final class CoreSessionCloser
{
    public function close(string $bearer): bool
    {
        $base = config('services.gamad_core.url');

        if (! is_string($base) || ! str_starts_with($base, 'https://')
            || trim($bearer) === '') {
            return false;
        }

        try {
            $response = Http::withToken($bearer)
                ->acceptJson()
                ->timeout(5)
                ->delete(rtrim($base, '/').'/api/v1/sessions/current');
        } catch (\Throwable) {
            return false;
        }

        if (! $response->ok()) {
            return false;
        }

        $data = $response->json();

        return is_array($data) && ($data['revoquee'] ?? null) === true;
    }
}
