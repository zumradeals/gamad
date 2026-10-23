<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Zumra;
use Illuminate\Auth\Access\Response;

/** Qui peut créer une ZUMRA, la modifier et voir ses membres (D-111, D-112). Toute règle vit ici, côté serveur. */
class ZumraPolicy
{
    /** Le Carrefour et l'en-tête d'une ZUMRA sont publics (D-112), visiteurs sans compte compris. */
    public function view(?User $viewer, Zumra $zumra): bool
    {
        return true;
    }

    /** Créer : toute personne connectée dont l'adresse e-mail est vérifiée. */
    public function create(User $user): Response
    {
        return $user->hasVerifiedEmail() ? Response::allow() : Response::deny('Vérifiez votre adresse e-mail pour créer une ZUMRA.');
    }

    /** Modifier la mission, la ville et le format : le fondateur et les responsables. */
    public function update(User $user, Zumra $zumra): Response
    {
        return $zumra->roleOf($user)?->manages() ? Response::allow() : Response::denyAsNotFound();
    }

    /** La liste des membres : les personnes connectées et vérifiées seulement (D-112). */
    public function viewMembers(?User $viewer, Zumra $zumra): bool
    {
        return (bool) $viewer?->hasVerifiedEmail();
    }
}
