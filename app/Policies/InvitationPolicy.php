<?php

namespace App\Policies;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Répondre à une invitation : la personne invitée seule, et vérifiée. */
class InvitationPolicy
{
    public function respond(User $user, Invitation $invitation): Response
    {
        if ($user->getKey() !== $invitation->user_id) {
            return Response::denyAsNotFound();
        }

        return $user->hasVerifiedEmail() ? Response::allow() : Response::deny('Vérifiez votre adresse e-mail pour répondre.');
    }
}
