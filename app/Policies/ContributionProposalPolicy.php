<?php

namespace App\Policies;

use App\Enums\ProposalState;
use App\Models\ContributionProposal;
use App\Models\User;
use App\Services\ContributionAccess;
use App\Services\People;
use App\Support\ContributionsModule;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Qui voit et traite une proposition de contribution (C1, D-207). Toute règle vit ici, côté serveur : l'affichage ne fait que la refléter.
 * Module fermé, aucune proposition n'existe (404), pour personne.
 */
class ContributionProposalPolicy
{
    public function __construct(private readonly ContributionAccess $access) {}

    public function before(?User $user): ?Response
    {
        return ContributionsModule::accessibleTo($user) ? null : Response::denyAsNotFound();
    }

    /**
     * Voir : la personne qui propose, les représentants du bénéficiaire, et — seulement si la proposition est publique, encore active
     * et son bénéficiaire accessible — toute personne vérifiée. Une proposition privée n'existe pas pour les autres (404).
     */
    public function view(?User $viewer, ContributionProposal $proposal): Response
    {
        if (! $viewer) {
            return Response::denyAsNotFound();
        }

        if ($proposal->user_id === $viewer->getKey() || $this->access->handles($viewer, $proposal)) {
            return Response::allow();
        }

        if (! $viewer->hasVerifiedEmail() || ! $proposal->isPublic() || ! in_array($proposal->state, [ProposalState::Proposed, ProposalState::Accepted, ProposalState::Declared, ProposalState::Realized], true)) {
            return Response::denyAsNotFound();
        }

        if (! $proposal->author?->isActive() || People::blocked($viewer, $proposal->author)) {
            return Response::denyAsNotFound();
        }

        [$kind, $beneficiary] = $this->access->beneficiary($proposal);

        if (! $this->access->exists($viewer, $kind, $beneficiary)) {
            return Response::denyAsNotFound();
        }

        if ($proposal->need_id && ! ($proposal->need && Gate::forUser($viewer)->allows('view', $proposal->need))) {
            return Response::denyAsNotFound();
        }

        return Response::allow();
    }

    /** Retirer : la personne qui propose, tant que la réalisation n'est pas confirmée. */
    public function withdraw(User $user, ContributionProposal $proposal): Response
    {
        if ($proposal->user_id !== $user->getKey()) {
            return Response::denyAsNotFound();
        }

        return $proposal->state->isOpen() ? Response::allow() : Response::deny('Cette proposition est déjà close.');
    }

    /** Accepter ou refuser : un représentant du bénéficiaire, une proposition en attente, un besoin encore ouvert. */
    public function decide(User $user, ContributionProposal $proposal): Response
    {
        if (! $this->access->handles($user, $proposal)) {
            return Response::denyAsNotFound();
        }

        if ($proposal->state !== ProposalState::Proposed) {
            return Response::deny('Cette proposition a déjà reçu une réponse.');
        }

        if ($proposal->need_id && ! ($proposal->need?->need_state?->isActive() ?? false)) {
            return Response::deny('Ce besoin est clos : il ne reçoit plus de proposition.');
        }

        return Response::allow();
    }

    /** Déclarer sa contribution réalisée : la personne qui propose, une fois la proposition acceptée. */
    public function declare(User $user, ContributionProposal $proposal): Response
    {
        if ($proposal->user_id !== $user->getKey()) {
            return Response::denyAsNotFound();
        }

        return $proposal->state === ProposalState::Accepted ? Response::allow() : Response::deny('Seule une proposition acceptée peut être déclarée réalisée.');
    }

    /** Confirmer la réalisation : un représentant du bénéficiaire, après acceptation. Une acceptation ne vaut jamais réalisation. */
    public function confirm(User $user, ContributionProposal $proposal): Response
    {
        if (! $this->access->handles($user, $proposal)) {
            return Response::denyAsNotFound();
        }

        return in_array($proposal->state, [ProposalState::Accepted, ProposalState::Declared], true)
            ? Response::allow()
            : Response::deny('On confirme la réalisation d’une contribution acceptée.');
    }
}
