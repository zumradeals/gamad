<?php

namespace App\Services;

use App\Models\MatchingSearch;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CoreConsoleActions
{
    private function authorize(User $actor, string $reason): string
    {
        Gate::forUser($actor)->authorize('super-admin');
        Validator::make(['reason' => trim($reason)], ['reason' => 'required|string|min:10|max:500'])->validate();

        return trim($reason);
    }

    public function retry(User $actor, string $id, string $reason): array
    {
        $reason = $this->authorize($actor, $reason);

        return DB::transaction(function () use ($actor, $id, $reason) {
            $search = MatchingSearch::query()->lockForUpdate()->findOrFail($id);
            abort_unless($search->state === MatchingSearch::FAILED, 422, 'Cette recherche n’est pas en échec définitif.');
            $matching = app(Matching::class);
            $publication = $search->publication_id === null ? null : Publication::query()->with('author.profile')->lockForUpdate()->find($search->publication_id);
            $person = $search->user_id === null ? null : User::query()->with('profile')->lockForUpdate()->find($search->user_id);
            $valid = $publication !== null ? $matching->isSearchable($publication)
                : ($person !== null && $person->isActive() && $person->profile !== null && $matching->isReady($person->profile));
            if (! $valid) {
                AdminJournal::record($actor, 'console.matching.retry', $search, $reason, ['result' => 'refused_obsolete']);

                return ['result' => 'refused_obsolete', 'message' => 'Relance refusée : le sujet, ses droits ou ses préférences ne permettent plus cette recherche.'];
            }
            // Repeated interventions reuse an open search without setting rerun on an already running one.
            $column = $search->publication_id !== null ? 'publication_id' : 'user_id';
            $open = MatchingSearch::query()->where($column, $search->$column)->whereIn('state', [MatchingSearch::QUEUED, MatchingSearch::RUNNING])->lockForUpdate()->first();
            $next = $open ?? app(MatchingSearches::class)->request($publication !== null ? MatchingSearches::FOR_PUBLICATION : MatchingSearches::FOR_PERSON, $search->$column, rerunRunning: false);
            $result = $open === null ? 'requested' : 'already_open';
            AdminJournal::record($actor, 'console.matching.retry', $search, $reason, ['result' => $result, 'search' => $next->id]);

            return ['result' => $result, 'message' => $open === null ? 'Recherche demandée. Son résultat reste à observer.' : 'Une recherche est déjà ouverte ; aucun traitement concurrent ajouté.'];
        });
    }

    public function requestRunner(User $actor, string $reason): void
    {
        $reason = $this->authorize($actor, $reason);
        if (request()->attributes->has('gamad.console.runner')) {
            return;
        }
        $entry = AdminJournal::record($actor, 'console.runner.request', reason: $reason, payload: ['result' => 'requested_after_response']);
        request()->attributes->set('gamad.console.runner', $entry->id);
    }
}
