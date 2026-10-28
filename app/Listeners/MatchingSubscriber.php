<?php

namespace App\Listeners;

use App\Events\Matching\PersonBlockChanged;
use App\Events\Matching\PersonSuspensionChanged;
use App\Events\Matching\ProfileMatchingChanged;
use App\Events\Matching\ProfileSkillsChanged;
use App\Events\Matching\PublicationMatchingChanged;
use App\Services\Matching;
use App\Services\MatchingSearches;
use App\Services\MatchingWithdrawals;

/**
 * GAMAD Social → Matching. Deux régimes, volontairement distincts :
 *  - les protections (retraits) s'appliquent tout de suite, dans la transaction du changement ;
 *  - les recalculs se demandent à l'exécution (MatchingSearches), qui les traite ensuite, sans bloquer la personne.
 */
class MatchingSubscriber
{
    public function __construct(private readonly Matching $matching, private readonly MatchingSearches $searches, private readonly MatchingWithdrawals $withdrawals) {}

    public function publication(PublicationMatchingChanged $event): void
    {
        $post = $event->publication;
        $post->loadMissing('author.profile');

        if ($this->matching->isSearchable($post)) {
            // Rouverte, démasquée ou nouvelle : une recherche compte les personnes qui peuvent aider (revenir ne réalerte jamais).
            if ($event->mayAddMatches() && ($post->matching['enabled'] ?? false) && ($post->matching['open'] ?? false)) {
                $this->searches->request(MatchingSearches::FOR_PUBLICATION, $post->getKey());
            }

            return;
        }

        if (! $event->created) {
            $this->withdrawals->forPublication($post, $this->reason($post));
        }
    }

    public function profile(ProfileMatchingChanged $event): void
    {
        $this->matching->settleProfile($event->user);

        if ($event->revoked) {
            $this->withdrawals->forPerson($event->user, MatchingWithdrawals::CONSENT);

            return;
        }

        $this->searches->request(MatchingSearches::FOR_PERSON, $event->user->getKey());
    }

    public function skills(ProfileSkillsChanged $event): void
    {
        if ($event->user->isActive() && $this->matching->preferences($event->user->ensureProfile())['enabled']) {
            $this->searches->request(MatchingSearches::FOR_PERSON, $event->user->getKey());
        }
    }

    public function block(PersonBlockChanged $event): void
    {
        if ($event->blocked) {
            $this->withdrawals->between($event->actor, $event->other);

            return;
        }

        // Un blocage levé ne rend rien d'office : chacun relance sa recherche, qui revérifie tous les droits.
        foreach ([$event->actor, $event->other] as $person) {
            if ($person->isActive() && $this->matching->preferences($person->ensureProfile())['enabled']) {
                $this->searches->request(MatchingSearches::FOR_PERSON, $person->getKey());
            }
        }
    }

    public function suspension(PersonSuspensionChanged $event): void
    {
        if ($event->suspended) {
            $this->withdrawals->forPerson($event->user, MatchingWithdrawals::SUSPENDED);

            return;
        }

        if ($this->matching->preferences($event->user->ensureProfile())['enabled']) {
            $this->searches->request(MatchingSearches::FOR_PERSON, $event->user->getKey());
        }
    }

    /** Le motif du retrait, pour la mesure : celui qui rend la publication inéligible. */
    private function reason($post): string
    {
        $cfg = $this->matching->settings($post);

        return match (true) {
            $post->isHidden() => MatchingWithdrawals::HIDDEN,
            $post->isNeed() && $post->need_state?->value === 'resolved' => MatchingWithdrawals::RESOLVED,
            $post->isNeed() && ! ($post->need_state?->isActive() ?? false) => MatchingWithdrawals::CLOSED,
            ! $post->author->isActive() => MatchingWithdrawals::SUSPENDED,
            $cfg['until'] && $cfg['until'] < today('Africa/Abidjan')->toDateString() => MatchingWithdrawals::EXPIRED,
            ! ($cfg['enabled'] && $cfg['open']) => MatchingWithdrawals::CLOSED,
            default => MatchingWithdrawals::UNAVAILABLE,
        };
    }
}
