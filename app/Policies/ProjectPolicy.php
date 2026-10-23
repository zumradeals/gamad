<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Models\Zumra;
use Illuminate\Auth\Access\Response;

/** Qui voit et gère un projet (D-005, D-127). Toute règle vit ici, côté serveur. */
class ProjectPolicy
{
    /** La page d'un projet est publique : visiteurs sans compte compris (D-005). */
    public function view(?User $viewer, Project $project): bool
    {
        return true;
    }

    /** Créer un projet dans une ZUMRA : son fondateur et ses responsables (D-127). */
    public function create(User $user, Zumra $zumra): Response
    {
        return $zumra->roleOf($user)?->manages() ? Response::allow() : Response::denyAsNotFound();
    }

    /** Modifier un projet et changer son état : le fondateur et les responsables de sa ZUMRA (D-127). */
    public function update(User $user, Project $project): Response
    {
        return $project->zumra->roleOf($user)?->manages() ? Response::allow() : Response::denyAsNotFound();
    }
}
