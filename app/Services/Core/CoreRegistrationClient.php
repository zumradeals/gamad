<?php

namespace App\Services\Core;

use Illuminate\Support\Facades\Http;

/**
 * Client d'inscription : le Core demeure seul créateur de l'identité.
 * Le contrat public Core doit être livré avant toute activation UI.
 */
final class CoreRegistrationClient
{
    /** @return array{identite: string, verification_requise: bool}|null */
    public function register(string $name, string $email, string $password): ?array
    {
        $base = config('services.gamad_core.url');

        if (! is_string($base) || ! str_starts_with($base, 'https://')
            || trim($name) === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || $password === '') {
            return null;
        }

        try {
            $response = Http::acceptJson()
                ->timeout(5)
                ->post(rtrim($base, '/').'/api/v1/inscriptions/publiques', [
                    'nom' => $name,
                    'email' => $email,
                    'secret' => $password,
                ]);
        } catch (\Throwable) {
            return null;
        }

        if ($response->status() !== 201) {
            return null;
        }

        $body = $response->json();

        if (! is_array($body) || ! is_string($body['identite'] ?? null)
            || $body['identite'] === ''
            || ! is_bool($body['verification_requise'] ?? null)) {
            return null;
        }

        return [
            'identite' => $body['identite'],
            'verification_requise' => $body['verification_requise'],
        ];
    }
}
