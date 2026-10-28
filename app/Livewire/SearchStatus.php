<?php

namespace App\Livewire;

use App\Models\MatchSuggestion;
use App\Models\Publication;
use App\Services\MatchingSearches;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * L'état réel d'une recherche de correspondances, vivant : tant qu'elle n'est pas terminée, il se rafraîchit tout seul (toutes les 5 s, ou
 * toutes les 30 s pendant l'attente d'une reprise, et seulement quand il est visible à l'écran) ; une fois terminée ou indisponible, plus aucune
 * requête. Chaque rafraîchissement est aussi une requête web : c'est elle qui fait avancer l'exécution tant que la page reste ouverte.
 * Un changement d'état prévient la liste des pistes pour qu'elle se recharge.
 */
class SearchStatus extends Component
{
    #[Locked]
    public string $kind = MatchingSearches::FOR_PERSON;

    #[Locked]
    public ?string $subjectId = null;

    public ?string $seen = null;

    public ?string $notice = null;

    public function mount(string $kind = MatchingSearches::FOR_PERSON, ?string $subjectId = null): void
    {
        $this->kind = $kind;
        $this->subjectId = $subjectId;
    }

    /** Relancer une recherche indisponible (au plus trois fois en dix minutes). */
    public function retry(MatchingSearches $searches): void
    {
        $user = Auth::user();
        abort_unless($user?->isActive(), 403);

        if (! RateLimiter::attempt('matching-retry:'.$user->id, 3, fn () => true, 600)) {
            $this->notice = 'Merci de patienter quelques minutes avant de relancer la recherche.';

            return;
        }

        $this->kind === MatchingSearches::FOR_PUBLICATION
            ? $searches->request(MatchingSearches::FOR_PUBLICATION, $this->publication()->getKey())
            : $searches->request(MatchingSearches::FOR_PERSON, $user->getKey());
        $this->notice = 'La recherche a été relancée.';
    }

    public function render(MatchingSearches $searches)
    {
        $user = Auth::user();
        abort_unless($user?->isActive(), 403);

        if ($this->kind === MatchingSearches::FOR_PUBLICATION) {
            $publication = $this->publication();
            $search = $searches->latestForPublication($publication->getKey());
            $count = MatchSuggestion::query()->where('user_id', $user->getKey())->where('publication_id', $publication->getKey())->whereNull('ignored_at')->whereNull('withdrawn_at')->count();
        } else {
            $search = $searches->latestForPerson($user);
            $count = null;
        }

        $status = $search?->status();
        $signature = $search === null ? null : $status.':'.$search->produced;

        // Un changement réel (état ou pistes produites) recharge la liste des pistes ; le premier affichage ne signale rien.
        if ($this->seen !== null && $signature !== $this->seen) {
            $this->dispatch('matching-search-updated');
        }
        $this->seen = $signature;

        return view('livewire.search-status', [
            'status' => $status === 'cancelled' ? null : $status,
            // Rafraîchissement seulement pendant les états non terminaux : 5 s en attente ou en cours, 30 s en attente d'une reprise.
            'poll' => match ($status) {
                'queued', 'running' => '5s',
                'retrying' => '30s',
                default => null,
            },
            'count' => $count,
        ]);
    }

    /** La publication, seulement pour qui la gère (la propriété `subjectId` est verrouillée contre la falsification). */
    private function publication(): Publication
    {
        $publication = Publication::query()->find($this->subjectId);
        abort_unless($publication !== null && $publication->isManagedBy(Auth::user()), 404);

        return $publication;
    }
}
