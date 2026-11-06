<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\Http;

/**
 * R02.1: lecture du contrat de session de GAMAD Core.
 * Ne crée aucune identité locale, session Laravel ni jeton fédéré.
 */
final class CoreSessionReader
{
    public function current(string $bearer): ?array
    {
        $base = config('services.gamad_core.url');
        if (! is_string($base) || ! str_starts_with($base, 'https://') || trim($bearer) === '') {
            return null;
        }

        try {
            $response = Http::withToken($bearer)
                ->acceptJson()
                ->timeout(5)
                ->get(rtrim($base, '/').'/api/v1/sessions/current');
        } catch (\Throwable) {
            return null;
        }

        if (! $response->ok()) {
            return null;
        }

        $data = $response->json();
        if (! is_array($data)
            || ! is_string($data['entite'] ?? null)
            || trim($data['entite']) === ''
            || ! is_string($data['expire_le'] ?? null)
            || trim($data['expire_le']) === '') {
            return null;
        }

        return [
            'entite' => $data['entite'],
            'assurance' => $data['assurance'] ?? null,
            'expire_le' => $data['expire_le'],
        ];
    }
}
