<?php

namespace App\Services;

use App\Enums\NeedState;
use App\Enums\ProposalState;
use App\Enums\PublicationType;
use App\Models\ContributionProposal;
use App\Models\Publication;
use App\Models\User;

/**
 * Compteurs du module Contributions (C1, D-207) : uniquement des faits enregistrés, jamais un montant. Les intentions, les engagements
 * acceptés et les réalisations confirmées sont comptés séparément. Les compteurs issus de propositions (qui peuvent être privées) ne
 * donnent pas leur chiffre exact sous le seuil d'anonymat : « moins de 5 ». Aucun classement ni nom de personne.
 */
class ContributionStats
{
    public const THRESHOLD = 5;

    /** @return array{needs: int, proposals: int, accepted: int, realized: int, satisfied: int} */
    public function community(): array
    {
        $needs = Publication::query()->where('type', PublicationType::Need->value)->whereNotNull('help_kind')->whereNull('hidden_at');
        $proposals = ContributionProposal::query();

        return [
            'needs' => (clone $needs)->count(),
            'proposals' => (clone $proposals)->count(),
            'accepted' => (clone $proposals)->whereIn('state', [ProposalState::Accepted->value, ProposalState::Declared->value, ProposalState::Realized->value])->count(),
            'realized' => (clone $proposals)->where('state', ProposalState::Realized->value)->count(),
            'satisfied' => (clone $needs)->where('need_state', NeedState::Resolved->value)->count(),
        ];
    }

    /** Un compteur issu de propositions : exact à partir du seuil, sinon « moins de 5 ». */
    public static function anonymised(int $count): string
    {
        return $count === 0 ? '0' : ($count < self::THRESHOLD ? '< '.self::THRESHOLD : (string) $count);
    }

    /** @return array{proposed: int, in_progress: int, realized: int, needs: int, to_handle: int} */
    public function personal(User $user): array
    {
        $mine = ContributionProposal::query()->where('user_id', $user->getKey());

        return [
            'proposed' => (clone $mine)->count(),
            'in_progress' => (clone $mine)->whereIn('state', [ProposalState::Accepted->value, ProposalState::Declared->value])->count(),
            'realized' => (clone $mine)->where('state', ProposalState::Realized->value)->count(),
            'needs' => Publication::query()->where('author_id', $user->getKey())->where('type', PublicationType::Need->value)->whereNotNull('help_kind')->count(),
            'to_handle' => app(ContributionFeed::class)->handledBy($user)->where('state', ProposalState::Proposed->value)->count(),
        ];
    }
}
