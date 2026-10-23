<?php

namespace App\Policies;

use App\Enums\JoinRequestState;
use App\Enums\ProjectState;
use App\Models\ParticipationRequest;
use App\Models\Project;
use App\Models\ProjectMember;
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

    /** L'équipe : la liste des personnes se lit connecté et vérifié, avec la règle de visibilité de chaque profil (D-140). */
    public function viewTeam(?User $viewer, Project $project): bool
    {
        return (bool) $viewer?->hasVerifiedEmail();
    }

    /**
     * Demander à participer (D-128) : un membre vérifié de la ZUMRA du projet, qui n'est pas déjà dans l'équipe, sans demande en
     * attente, tant que le projet n'est ni terminé ni abandonné. Le fondateur et les responsables s'ajoutent directement. Une
     * demande déclinée ne se refait pas ; une demande retirée se refait.
     */
    public function participate(User $user, Project $project): Response
    {
        if (! $user->hasVerifiedEmail()) {
            return Response::deny('Vérifiez votre adresse e-mail pour participer.');
        }

        $role = $project->zumra->roleOf($user);

        if (! $role) {
            return Response::deny('Pour participer, rejoignez d’abord la ZUMRA.');
        }

        if ($role->manages()) {
            return Response::deny('Vous gérez ce projet : ajoutez-vous directement à l’équipe.');
        }

        if (! in_array($project->state, [ProjectState::Idea, ProjectState::InProgress], true)) {
            return Response::deny('Ce projet n’accepte plus de participation.');
        }

        if (ProjectMember::query()->where('project_id', $project->getKey())->where('user_id', $user->getKey())->exists()) {
            return Response::deny('Vous êtes déjà dans l’équipe.');
        }

        $state = ParticipationRequest::query()->where('project_id', $project->getKey())->where('user_id', $user->getKey())->first()?->state;

        return match ($state) {
            JoinRequestState::Pending => Response::deny('Votre demande est déjà envoyée.'),
            JoinRequestState::Declined => Response::deny('Votre demande n’a pas été retenue.'),
            default => Response::allow(),
        };
    }
}
