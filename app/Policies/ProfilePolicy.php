<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Qui peut voir un profil (D-067). Un profil caché n'existe pas pour celui qui n'y a pas droit : la réponse est 404, jamais
 * 403, pour ne pas révéler qu'il existe. Même règle, côté requêtes, que Profile::scopeVisibleTo().
 */
class ProfilePolicy
{
    public function view(?User $viewer, Profile $profile): Response
    {
        if ($viewer && $viewer->getKey() === $profile->user_id) {
            return Response::allow();
        }

        if (! $profile->user->isActive()) {
            return Response::denyAsNotFound();
        }

        return $profile->isPublic() || $viewer?->hasVerifiedEmail()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
