<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/** Une conversation privée n'existe que pour ses deux participants : pour tout autre, elle répond 404. */
class ConversationPolicy
{
    public function view(?User $user, Conversation $conversation): Response
    {
        return $user && $user->hasVerifiedEmail() && $conversation->isParticipant($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /** Écrire : un participant, tant que l'autre personne existe encore (D-097). */
    public function send(?User $user, Conversation $conversation): Response
    {
        $view = $this->view($user, $conversation);

        if (! $view->allowed()) {
            return $view;
        }

        return $conversation->otherPerson($user) ? Response::allow() : Response::deny('Cette personne a supprimé son compte : vous ne pouvez plus lui écrire.');
    }
}
