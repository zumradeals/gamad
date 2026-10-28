<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Models\MatchSuggestion;
use App\Models\Publication;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Retrait IMMÉDIAT des pistes devenues interdites ou obsolètes. Il s'exécute dans la transaction du changement qui le provoque
 * (masquage, clôture, blocage, retrait du consentement, suspension) : la confidentialité ne dépend jamais d'un traitement différé.
 * Les droits sont de toute façon revérifiés à chaque lecture (MatchSuggestionPolicy, Matching::detail) ; ici, l'état persistant les suit.
 *
 * Une piste retirée garde sa ligne (mesure, absence de nouvelle alerte) mais n'est plus exposée.
 */
class MatchingWithdrawals
{
    public const HIDDEN = 'hidden';

    public const CLOSED = 'closed';

    public const RESOLVED = 'resolved';

    public const EXPIRED = 'expired';

    public const BLOCKED = 'blocked';

    public const CONSENT = 'consent';

    public const UNAVAILABLE = 'unavailable';

    public const SUSPENDED = 'suspended';

    public function __construct(private readonly MatchingSearches $searches) {}

    /** Toutes les pistes qui reposent sur cette publication, chez toutes les personnes. @return int Nombre de pistes retirées. */
    public function forPublication(Publication $publication, string $reason): int
    {
        $this->searches->cancelFor(MatchingSearches::FOR_PUBLICATION, $publication->getKey(), 'withdrawn');

        return $this->withdraw(MatchSuggestion::query()->where(fn (Builder $q) => $q->where('publication_id', $publication->getKey())->orWhere('offer_id', $publication->getKey())), $reason);
    }

    /**
     * Tout ce qui concerne une personne qui n'est plus joignable par le Matching (consentement retiré, préférences désactivées,
     * indisponibilité, suspension) : ses propres pistes, celles des autres qui la montrent, celles qui reposent sur ses publications.
     */
    public function forPerson(User $user, string $reason): int
    {
        $this->searches->cancelFor(MatchingSearches::FOR_PERSON, $user->getKey(), 'withdrawn');
        $ownPublications = fn ($query) => $query->select('id')->from('publications')->where('author_id', $user->getKey());
        $profile = fn ($query) => $query->select('id')->from('profiles')->where('user_id', $user->getKey());

        return $this->withdraw(MatchSuggestion::query()->where(fn (Builder $q) => $q
            ->where('user_id', $user->getKey())
            ->orWhereIn('profile_id', $profile)
            ->orWhereIn('publication_id', $ownPublications)
            ->orWhereIn('offer_id', $ownPublications)), $reason);
    }

    /** Les pistes qui rapprochaient exactement ces deux personnes, chez l'une comme chez l'autre. */
    public function between(User $first, User $second, string $reason = self::BLOCKED): int
    {
        $ids = [$first->getKey(), $second->getKey()];
        $publications = fn ($query) => $query->select('id')->from('publications')->whereIn('author_id', $ids);
        $profiles = fn ($query) => $query->select('id')->from('profiles')->whereIn('user_id', $ids);

        return $this->withdraw(MatchSuggestion::query()->whereIn('user_id', $ids)->whereIn('publication_id', $publications)
            ->where(fn (Builder $q) => $q->whereIn('profile_id', $profiles)->orWhereIn('offer_id', $publications)), $reason);
    }

    private function withdraw(Builder $rows, string $reason): int
    {
        return DB::transaction(function () use ($rows, $reason) {
            $rows = $rows->whereNull('withdrawn_at');
            $users = (clone $rows)->distinct()->pluck('user_id');
            $count = $rows->update(['withdrawn_at' => now(), 'withdrawn_reason' => $reason]);
            $this->settleAlerts($users->all());

            return $count;
        });
    }

    /** Une personne qui n'a plus aucune piste active ne garde pas une alerte qui ne mène à rien. */
    private function settleAlerts(array $userIds): void
    {
        if ($userIds === []) {
            return;
        }

        UserNotification::query()->whereIn('user_id', $userIds)->where('kind', NotificationKind::MatchFound)->whereNull('read_at')
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))->from('match_suggestions')
                ->whereColumn('match_suggestions.user_id', 'user_notifications.user_id')->whereNull('ignored_at')->whereNull('withdrawn_at'))
            ->update(['read_at' => now()]);
    }
}
