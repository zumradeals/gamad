<?php

namespace App\Livewire;

use App\Models\MatchSuggestion;
use App\Services\Matching;
use App\Services\MatchingSearches;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Component;

class MatchSuggestions extends Component
{
    public int $limit = 10;

    public ?string $status = null;

    public function more(): void
    {
        $this->limit = min(100, $this->limit + 10);
    }

    public function ignore(string $id, Matching $matching): void
    {
        abort_unless(Str::isUuid($id), 404);
        $matching->ignore(Auth::user(), MatchSuggestion::findOrFail($id));
        $this->status = 'Cette piste a été retirée de vos suggestions.';
    }

    /** Relancer une recherche indisponible (au plus trois fois en dix minutes). */
    public function retrySearch(MatchingSearches $searches): void
    {
        $user = Auth::user();
        abort_unless($user?->isActive(), 403);

        if (! RateLimiter::attempt('matching-retry:'.$user->id, 3, fn () => true, 600)) {
            $this->status = 'Merci de patienter quelques minutes avant de relancer la recherche.';

            return;
        }

        $searches->request(MatchingSearches::FOR_PERSON, $user->id);
        $this->status = 'La recherche a été relancée.';
    }

    public function render(Matching $matching, MatchingSearches $searches)
    {
        $user = Auth::user();
        abort_unless($user?->isActive(), 403);
        $this->limit = max(10, min(100, $this->limit));
        $preferences = $matching->preferences($user->ensureProfile());
        $search = $searches->latestForPerson($user);

        // Première visite d'une personne qui a déjà choisi ses préférences : sa recherche est demandée (elle ne se calcule pas ici).
        if ($preferences['enabled'] && $preferences['available'] && $search === null) {
            $search = $searches->request(MatchingSearches::FOR_PERSON, $user->id);
        }

        $items = $preferences['enabled'] ? $matching->suggestions($user, $this->limit) : collect();

        return view('livewire.match-suggestions', ['items' => $items->take($this->limit), 'preferences' => $preferences, 'hasMore' => $items->count() > $this->limit && $this->limit < 100, 'search' => $search]);
    }
}
