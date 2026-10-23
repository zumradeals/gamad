<?php

namespace App\Policies;

use App\Models\Gratitude;
use App\Models\User;

/** Masquer un remerciement de son profil : la personne remerciée seule (D-084). */
class GratitudePolicy
{
    public function hide(User $user, Gratitude $gratitude): bool
    {
        return $user->getKey() === $gratitude->helper_id;
    }
}
