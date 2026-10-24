<?php

namespace App\Policies;

use App\Models\MatchSuggestion;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MatchSuggestionPolicy
{
    public function view(User $user, MatchSuggestion $suggestion): Response
    {
        return $user->isActive() && $suggestion->user_id === $user->id ? Response::allow() : Response::denyAsNotFound();
    }

    public function ignore(User $user, MatchSuggestion $suggestion): Response
    {
        return $this->view($user, $suggestion);
    }
}
