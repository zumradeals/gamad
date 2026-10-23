<?php

namespace App\Policies;

use App\Enums\GestureState;
use App\Models\Gesture;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Qui peut décider d'un geste, et qui peut le retirer (D-082, D-083). */
class GesturePolicy
{
    /**
     * Accepter ou décliner : l'auteur de la publication seul, sur un geste en attente, tant que la publication accepte des
     * gestes (un besoin résolu ou fermé n'en accepte plus).
     */
    public function decide(User $user, Gesture $gesture): Response
    {
        $publication = $gesture->publication;

        if ($user->getKey() !== $publication->author_id) {
            return Response::deny('Seul l’auteur de la publication décide.');
        }

        if ($gesture->state !== GestureState::Sent) {
            return Response::deny('Ce geste a déjà été traité.');
        }

        return $publication->acceptsGestures() ? Response::allow() : Response::deny('Ce besoin est clos.');
    }

    /** Retirer son geste : la personne qui l'a envoyé, tant qu'il n'est pas accepté (D-082). */
    public function withdraw(User $user, Gesture $gesture): Response
    {
        if ($user->getKey() !== $gesture->user_id) {
            return Response::deny('Ce geste n’est pas le vôtre.');
        }

        return $gesture->state === GestureState::Sent ? Response::allow() : Response::deny('Ce geste ne peut plus être retiré.');
    }
}
