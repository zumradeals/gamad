<?php

namespace App\Policies;

use App\Models\Company;
use App\Models\Opportunity;
use App\Models\OpportunityResponse;
use App\Models\User;
use App\Services\People;

/**
 * Qui peut voir, gérer et répondre à une opportunité. Seul le propriétaire d'une entreprise gère ses opportunités ;
 * seule une entreprise vérifiée publie. Aucune personne physique ne publie dans ce module.
 */
class OpportunityPolicy
{
    private function active(?User $user): bool
    {
        return $user !== null && $user->fresh()?->isActive() && $user->hasVerifiedEmail();
    }

    /** Publiée ou clôturée : visible de tous si l'entreprise est vérifiée ; brouillon et archive : son propriétaire seul. */
    public function view(?User $viewer, Opportunity $opportunity): bool
    {
        return $opportunity->visibleTo($viewer);
    }

    /** Préparer, modifier, clôturer, archiver : propriétaire d'une entreprise non suspendue. */
    public function manage(User $user, Opportunity $opportunity): bool
    {
        return $this->manageFor($user, $opportunity->company);
    }

    public function manageFor(User $user, Company $company): bool
    {
        $company = $company->fresh();

        return $this->active($user) && $company !== null && $company->owner_id === $user->id && $company->status !== 'suspended';
    }

    /** Publier au public : l'entreprise doit être vérifiée par GAMAD. */
    public function publish(User $user, Opportunity $opportunity): bool
    {
        return $this->manage($user, $opportunity) && $opportunity->company->fresh()->verified();
    }

    public function publishFor(User $user, Company $company): bool
    {
        return $this->manageFor($user, $company) && $company->fresh()->verified();
    }

    /**
     * Répondre : une personne active, autre que le propriétaire de l'entreprise, sur une opportunité ouverte (D-200). Une candidature
     * relève de la relation professionnelle avec l'entreprise : un blocage entre la personne et le propriétaire ne l'empêche pas.
     * Les relations et conversations personnelles directes entre personnes bloquées restent interdites (People).
     */
    public function respond(User $user, Opportunity $opportunity): bool
    {
        return $this->active($user)
            && $opportunity->isOpen()
            && $opportunity->apply_mode === 'gamad'
            && $opportunity->company->owner_id !== $user->id;
    }

    /** Les réponses reçues, leurs messages et leurs pièces jointes : l'entreprise émettrice, jamais le public. */
    public function viewResponses(User $user, Opportunity $opportunity): bool
    {
        return $this->manage($user, $opportunity);
    }

    /** Une réponse est lisible par son auteur et par l'entreprise. */
    public function viewResponse(User $user, OpportunityResponse $response): bool
    {
        return $response->user_id === $user->id || $this->viewResponses($user, $response->opportunity);
    }
}
