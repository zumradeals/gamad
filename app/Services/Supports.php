<?php

namespace App\Services;

use App\Models\Publication;
use App\Models\PublicationSupport;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Le soutien « Je soutiens » d'une avancée de projet (D-139) : un engagement réel, compté, que l'on peut retirer. Aucune note,
 * aucun classement, aucune liste publique des personnes, aucune notification.
 */
class Supports
{
    /**
     * Soutient une avancée. Soutenir deux fois ne change rien (le soutien existe déjà).
     *
     * @throws ValidationException
     */
    public function support(User $user, Publication $publication): void
    {
        Gate::forUser($user)->authorize('support', $publication);

        if (PublicationSupport::query()->where('publication_id', $publication->getKey())->where('user_id', $user->getKey())->exists()) {
            return;
        }

        $this->throttle($user);

        try {
            $support = new PublicationSupport;
            $support->publication()->associate($publication);
            $support->user()->associate($user);
            $support->save();
        } catch (UniqueConstraintViolationException) {
            // Deux clics simultanés : le soutien existe déjà, comme voulu.
        }
    }

    /** Retire son soutien. Retirer ce qui n'existe pas ne change rien. Toute personne connectée peut le faire, même si l'avancée est masquée. */
    public function withdraw(User $user, Publication $publication): void
    {
        PublicationSupport::query()->where('publication_id', $publication->getKey())->where('user_id', $user->getKey())->delete();
    }

    /**
     * Parmi ces publications, celles que cette personne soutient : une seule requête pour une liste de cartes.
     *
     * @param  iterable<int, string>  $publicationIds
     * @return array<int, string>
     */
    public function supportedBy(?User $user, iterable $publicationIds): array
    {
        $ids = collect($publicationIds)->values();

        if (! $user || $ids->isEmpty()) {
            return [];
        }

        return PublicationSupport::query()->where('user_id', $user->getKey())->whereIn('publication_id', $ids)->pluck('publication_id')->all();
    }

    /** Limite de débit contre le spam (D-142) : un nombre de soutiens par heure et par personne. */
    private function throttle(User $user): void
    {
        $key = "support:{$user->getKey()}";

        if (RateLimiter::tooManyAttempts($key, config('gamad.limits.supports_per_hour'))) {
            throw ValidationException::withMessages(['support' => 'Vous soutenez trop vite. Réessayez dans un moment.']);
        }

        RateLimiter::hit($key, 3600);
    }
}
