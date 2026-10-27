<?php

namespace App\Policies;

use App\Enums\JoinRequestState;
use App\Enums\PublicationType;
use App\Enums\ZumraRole;
use App\Models\JoinRequest;
use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Support\ZumraModule;
use Illuminate\Auth\Access\Response;

/** Qui peut créer une ZUMRA, la modifier et voir ses membres (D-111, D-112). Toute règle vit ici, côté serveur. */
class ZumraPolicy
{
    /**
     * Fermeture totale (Z1, D-205) : module désactivé, ou en test privé pour une personne non autorisée, aucune ZUMRA n'existe (404) :
     * ni lecture, ni action, même pour un membre. Cette règle précède toutes les autres.
     */
    public function before(?User $user): ?Response
    {
        return ZumraModule::accessibleTo($user) ? null : Response::denyAsNotFound();
    }

    /** L'en-tête d'une ZUMRA est public (D-112), visiteurs sans compte compris, quand le module est ouvert à la personne. */
    public function view(?User $viewer, Zumra $zumra): bool
    {
        return true;
    }

    /** Créer : toute personne connectée dont l'adresse e-mail est vérifiée. */
    public function create(User $user): Response
    {
        if (! ZumraModule::accessibleTo($user)) {
            return Response::deny('Les ZUMRA ne sont pas ouvertes pour le moment. Retrouvez les personnes et les besoins dans le Fil.');
        }

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

    /** La Discussion : les membres vérifiés seulement (D-112). Pour toute autre personne, elle n'existe pas (404). */
    public function discuss(?User $user, Zumra $zumra): Response
    {
        return $user && $user->hasVerifiedEmail() && $zumra->roleOf($user)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Publier dans une ZUMRA (D-214) : le fondateur et les responsables publient les quatre types ; un membre vérifié publie un savoir
     * ou une offre d'aide (PublicationType::forZumraMember). Une personne qui n'est pas dans la ZUMRA n'y publie pas (404).
     */
    public function publish(User $user, Zumra $zumra, ?PublicationType $type = null): Response
    {
        $role = $zumra->roleOf($user);

        if (! $role) {
            return Response::denyAsNotFound();
        }

        if (! $user->hasVerifiedEmail()) {
            return Response::deny('Vérifiez votre adresse e-mail pour publier.');
        }

        // Un type qui n'existe pour aucune ZUMRA est refusé par l'appelant (message de validation ou 404) ; ici, seul le droit du rôle compte.
        $reserved = ! $role->manages() && $type !== null && in_array($type, PublicationType::forZumra(), true) && ! in_array($type, PublicationType::forZumraMember(), true);

        return $reserved ? Response::denyAsNotFound() : Response::allow();
    }

    /** Gérer les demandes, les invitations et les membres : le fondateur et les responsables (D-111). */
    public function manage(User $user, Zumra $zumra): Response
    {
        return $zumra->roleOf($user)?->manages() ? Response::allow() : Response::denyAsNotFound();
    }

    /**
     * Demander à rejoindre (D-110) : une personne vérifiée qui n'est pas déjà dans la ZUMRA, sans demande en attente. Une demande
     * déclinée ne se refait pas ; une demande retirée, ou d'une personne qui a quitté la ZUMRA depuis, se refait.
     */
    public function join(User $user, Zumra $zumra): Response
    {
        if (! ZumraModule::accessibleTo($user)) {
            return Response::deny('Les ZUMRA ne sont pas ouvertes pour le moment. Retrouvez les personnes et les besoins dans le Fil.');
        }

        if (! $user->hasVerifiedEmail()) {
            return Response::deny('Vérifiez votre adresse e-mail pour demander à rejoindre une ZUMRA.');
        }

        if ($zumra->roleOf($user)) {
            return Response::deny('Vous êtes déjà dans cette ZUMRA.');
        }

        $state = JoinRequest::query()->where('zumra_id', $zumra->getKey())->where('user_id', $user->getKey())->first()?->state;

        return match ($state) {
            JoinRequestState::Pending => Response::deny('Votre demande est déjà envoyée.'),
            JoinRequestState::Declined => Response::deny('Votre demande n’a pas été retenue.'),
            default => Response::allow(),
        };
    }

    /** Inviter des personnes par leurs capacités : le fondateur et les responsables. */
    public function invite(User $user, Zumra $zumra): Response
    {
        if (! ZumraModule::accessibleTo($user)) {
            return Response::deny('Les ZUMRA ne sont pas ouvertes pour le moment. Retrouvez les personnes et les besoins dans le Fil.');
        }

        return $this->manage($user, $zumra);
    }

    /** Quitter la ZUMRA : tout membre sauf le fondateur, qui ne peut pas la laisser sans fondateur. */
    public function leave(User $user, Zumra $zumra): Response
    {
        $role = $zumra->roleOf($user);

        if (! $role) {
            return Response::denyAsNotFound();
        }

        return $role === ZumraRole::Founder
            ? Response::deny('Le fondateur ne peut pas quitter sa ZUMRA.')
            : Response::allow();
    }

    /**
     * Retirer un membre : le fondateur retire un responsable ou un membre, un responsable retire un membre ; on ne retire ni le
     * fondateur, ni soi-même (on quitte).
     */
    public function removeMember(User $user, Zumra $zumra, ZumraMembership $target): Response
    {
        $role = $zumra->roleOf($user);

        if (! $role?->manages() || $target->zumra_id !== $zumra->getKey()) {
            return Response::denyAsNotFound();
        }

        if ($target->role === ZumraRole::Founder || $target->user_id === $user->getKey()) {
            return Response::deny('Cette personne ne peut pas être retirée.');
        }

        return $target->role === ZumraRole::Manager && $role !== ZumraRole::Founder
            ? Response::deny('Seul le fondateur retire un responsable.')
            : Response::allow();
    }

    /** Nommer ou retirer un responsable : le fondateur seul (D-111). */
    public function setManager(User $user, Zumra $zumra, ZumraMembership $target): Response
    {
        if ($zumra->roleOf($user) !== ZumraRole::Founder || $target->zumra_id !== $zumra->getKey()) {
            return Response::denyAsNotFound();
        }

        return $target->role === ZumraRole::Founder ? Response::deny('Le fondateur est déjà responsable de tout.') : Response::allow();
    }
}
