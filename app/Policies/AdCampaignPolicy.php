<?php

namespace App\Policies;

use App\Models\AdCampaign;
use App\Models\User;
use App\Support\PubliciteModule;
use Illuminate\Auth\Access\Response;

/**
 * Qui fait quoi sur une campagne (D-209 à D-212). Service fermé : aucune campagne n'existe (404), pour personne. Une campagne se voit et
 * se gère par son annonceur (la personne, ou le propriétaire de l'entreprise) ; la modération ne voit que les campagnes envoyées en
 * examen et ne modère jamais les siennes. Aucune campagne d'autrui n'est visible d'un annonceur.
 */
class AdCampaignPolicy
{
    public function before(?User $user): ?Response
    {
        return PubliciteModule::accessibleTo($user) ? null : Response::denyAsNotFound();
    }

    public function create(User $user): bool
    {
        return $user->hasVerifiedEmail() && ! $user->isSuspended();
    }

    /** Gérer (voir, modifier, mettre en pause, arrêter…) : l'annonceur seulement. */
    public function manage(User $user, AdCampaign $campaign): Response
    {
        return $this->isAdvertiser($user, $campaign) ? Response::allow() : Response::denyAsNotFound();
    }

    public function view(User $user, AdCampaign $campaign): Response
    {
        if ($this->isAdvertiser($user, $campaign)) {
            return Response::allow();
        }

        return $user->can('moderate') && $campaign->state !== 'draft' ? Response::allow() : Response::denyAsNotFound();
    }

    public function moderate(User $user, AdCampaign $campaign): Response
    {
        if (! $user->can('moderate')) {
            return Response::deny('Réservé à la modération.');
        }

        return $this->isAdvertiser($user, $campaign) ? Response::deny('Vous ne pouvez pas modérer votre propre annonce.') : Response::allow();
    }

    private function isAdvertiser(User $user, AdCampaign $campaign): bool
    {
        return $campaign->user_id === $user->getKey() || ($campaign->company_id !== null && $campaign->company?->owner_id === $user->getKey());
    }
}
