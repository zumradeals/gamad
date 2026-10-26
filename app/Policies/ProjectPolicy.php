<?php

namespace App\Policies;

use App\Enums\JoinRequestState;
use App\Enums\ProjectState;
use App\Models\ParticipationRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Zumra;
use App\Support\ZumraModule;
use Illuminate\Auth\Access\Response;

/**
 * Qui voit et gère un projet (P1, D-206). Toute règle vit ici, côté serveur.
 *
 * Un projet est privé : son dossier n'est ouvert qu'à son porteur et à ses collaborateurs. L'appartenance à la ZUMRA d'accueil ne donne
 * aucun accès au dossier ; ses responsables examinent seulement la demande d'accueil. La présentation publique est un choix du porteur.
 * Les projets suivent l'état du module ZUMRA : fermés, ils n'existent pour personne (D-205).
 */
class ProjectPolicy
{
    public function before(?User $user): ?Response
    {
        return ZumraModule::accessibleTo($user) ? null : Response::denyAsNotFound();
    }

    /** Ouvrir le dossier du projet : son porteur et ses collaborateurs, jamais par la seule appartenance à une ZUMRA. Pour les autres, il n'existe pas (404). */
    public function view(?User $viewer, Project $project): Response
    {
        if (! $viewer) {
            return Response::denyAsNotFound();
        }

        // Projet d'avant P1 dont le porteur n'existe plus : les responsables de sa ZUMRA le gardent.
        if ($project->owner_id === null && $project->zumra->roleOf($viewer)?->manages()) {
            return Response::allow();
        }

        return $project->isOwnedBy($viewer) || $project->isCollaborator($viewer) ? Response::allow() : Response::denyAsNotFound();
    }

    /** La présentation publique limitée : seulement si le porteur l'a publiée et que la ZUMRA a accepté l'accueil. */
    public function viewShowcase(?User $viewer, Project $project): bool
    {
        return $project->isShowcasePublished();
    }

    /**
     * Créer un projet (P1) : toute personne vérifiée. Dans une ZUMRA dont elle est membre, l'accueil est immédiat ; dans une autre, il
     * faut que la ZUMRA l'accepte, et le projet reste un brouillon privé en attendant.
     */
    public function create(User $user, Zumra $zumra): Response
    {
        return $user->hasVerifiedEmail() ? Response::allow() : Response::deny('Vérifiez votre adresse e-mail pour créer un projet.');
    }

    /** Modifier le projet, gérer son équipe, ses étapes, son accueil et sa présentation : son porteur seul (la ZUMRA ne dirige pas). */
    public function update(User $user, Project $project): Response
    {
        if ($project->isOwnedBy($user)) {
            return Response::allow();
        }

        // Projet d'avant P1 dont le porteur n'existe plus.
        if ($project->owner_id === null && $project->zumra->roleOf($user)?->manages()) {
            return Response::allow();
        }

        return Response::denyAsNotFound();
    }

    /** L'équipe : réservée à ceux qui ouvrent le dossier. */
    public function viewTeam(?User $viewer, Project $project): bool
    {
        return $this->view($viewer, $project)->allowed();
    }

    /** Examiner la demande d'accueil et l'accepter ou la refuser : le fondateur et les responsables de la ZUMRA demandée, sans accès au dossier. */
    public function decideHosting(User $user, Project $project): Response
    {
        return $project->zumra->roleOf($user)?->manages() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Proposer son aide (D-128, P1) : une personne vérifiée, hors du projet, quand le projet a publié sa présentation. Une demande
     * déclinée ne se refait pas ; une demande retirée se refait. Le porteur décide.
     */
    public function participate(User $user, Project $project): Response
    {
        if (! $user->hasVerifiedEmail()) {
            return Response::deny('Vérifiez votre adresse e-mail pour proposer votre aide.');
        }

        if (! $project->isShowcasePublished()) {
            return Response::denyAsNotFound();
        }

        if ($project->isOwnedBy($user)) {
            return Response::deny('C’est votre projet.');
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
