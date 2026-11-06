<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\Http;

/**
 * Authentification auprès de l'autorité GAMAD Core.
 * Aucun mot de passe ni jeton n'est persisté par ce client.
 */
final class CoreSessionOpener
{
    /** @return array{jeton: string, entite: string, expire_le: string}|null */
    public function open(string $identifier, string $secret): ?array
    {
        $base = config('services.gamad_core.url');

        if (! is_string($base) || ! str_starts_with($base, 'https://')
            || trim($identifier) === '' || $secret === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(5)
                ->post(rtrim($base, '/').'/api/v1/sessions', [
                    'identifiant' => $identifier,
                    'type_identifiant' => 'EMAIL',
                    'secret' => $secret,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if ($response->status() !== 201) {
            return null;
        }

        $body = $response->json();
        if (! is_array($body)
            || ($body['type'] ?? null) !== 'Bearer'
            || ! is_string($body['jeton'] ?? null) || $body['jeton'] === ''
            || ! is_string($body['entite'] ?? null) || $body['entite'] === ''
            || ! is_string($body['expire_le'] ?? null) || $body['expire_le'] === '') {
            return null;
        }

        return [
            'jeton' => $body['jeton'],
            'entite' => $body['entite'],
            'expire_le' => $body['expire_le'],
        ];
    }
}
