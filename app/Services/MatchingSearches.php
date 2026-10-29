<?php

namespace App\Services;

use App\Core\Execution\Execution;
use App\Jobs\Matching\RunMatchingSearch;
use App\Models\MatchingSearch;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Les recherches de correspondances et leur état. Une seule recherche est ouverte par sujet (garanti par la base) : une demande qui
 * arrive pendant qu'elle s'exécute la fera repartir une fois terminée, une demande qui arrive pendant qu'elle attend se confond avec elle.
 */
class MatchingSearches
{
    public const FOR_PUBLICATION = 'publication';

    public const FOR_PERSON = 'person';

    /** Demande une recherche pour une publication ou une personne. Idempotent. */
    public function request(string $kind, string $subjectId, bool $rerunRunning = true): MatchingSearch
    {
        $column = $kind === self::FOR_PUBLICATION ? 'publication_id' : 'user_id';

        try {
            return DB::transaction(function () use ($column, $subjectId, $rerunRunning) {
                $open = MatchingSearch::query()->where($column, $subjectId)->whereIn('state', [MatchingSearch::QUEUED, MatchingSearch::RUNNING])->lockForUpdate()->first();

                if ($open !== null) {
                    // En cours : elle repartira à la fin pour tenir compte du changement. En attente : elle le verra déjà.
                    if ($rerunRunning && $open->state === MatchingSearch::RUNNING && ! $open->rerun) {
                        $open->forceFill(['rerun' => true])->save();
                    }

                    return $open;
                }

                $search = MatchingSearch::query()->create([$column => $subjectId, 'state' => MatchingSearch::QUEUED, 'requested_at' => now()]);
                Execution::dispatch(new RunMatchingSearch($search->getKey()));

                return $search;
            });
        } catch (UniqueConstraintViolationException) {
            // Une autre requête vient d'ouvrir la même recherche : c'est elle qui compte.
            return MatchingSearch::query()->where($column, $subjectId)->whereIn('state', [MatchingSearch::QUEUED, MatchingSearch::RUNNING])->firstOrFail();
        }
    }

    /** La dernière recherche d'une personne (pour l'écran « Mes pistes »). */
    public function latestForPerson(User $user): ?MatchingSearch
    {
        return MatchingSearch::query()->where('user_id', $user->getKey())->orderByDesc('requested_at')->orderByDesc('id')->first();
    }

    public function latestForPublication(string $publicationId): ?MatchingSearch
    {
        return MatchingSearch::query()->where('publication_id', $publicationId)->orderByDesc('requested_at')->orderByDesc('id')->first();
    }

    /** Passe la recherche « en cours » (ou refuse si elle n'est plus ouverte : annulée, déjà terminée). */
    public function begin(string $id): ?MatchingSearch
    {
        return DB::transaction(function () use ($id) {
            $search = MatchingSearch::query()->whereKey($id)->lockForUpdate()->first();

            if ($search === null || ! $search->isOpen()) {
                return null;
            }

            $search->forceFill(['state' => MatchingSearch::RUNNING, 'started_at' => $search->started_at ?? now(), 'heartbeat_at' => now()])->save();

            return $search;
        });
    }

    /** Une tranche est faite : on garde la position et le nombre de pistes produites. */
    public function progress(MatchingSearch $search, int $produced, ?array $cursor): void
    {
        MatchingSearch::query()->whereKey($search->getKey())->where('state', MatchingSearch::RUNNING)
            ->update(['produced' => DB::raw('produced + '.(int) $produced), 'cursor' => $cursor === null ? null : json_encode($cursor), 'heartbeat_at' => now()]);
    }

    /** Terminée. Si une demande est arrivée entre-temps, une nouvelle recherche repart (jamais perdue). */
    public function finish(string $id, int $produced): void
    {
        $rerun = DB::transaction(function () use ($id, $produced) {
            $search = MatchingSearch::query()->whereKey($id)->lockForUpdate()->first();

            if ($search === null || $search->state !== MatchingSearch::RUNNING) {
                return null;
            }

            $search->forceFill(['state' => MatchingSearch::DONE, 'produced' => $search->produced + $produced, 'cursor' => null, 'finished_at' => now(), 'error_code' => null])->save();

            return $search->rerun ? $search : null;
        });

        if ($rerun !== null) {
            $this->request($rerun->publication_id !== null ? self::FOR_PUBLICATION : self::FOR_PERSON, $rerun->publication_id ?? $rerun->user_id);
        }
    }

    /** Une tentative a échoué : la recherche attend sa reprise (Laravel la relance avec des délais croissants). */
    public function retrying(string $id, string $errorCode): void
    {
        MatchingSearch::query()->whereKey($id)->whereIn('state', [MatchingSearch::QUEUED, MatchingSearch::RUNNING])
            ->update(['state' => MatchingSearch::QUEUED, 'attempts' => DB::raw('attempts + 1'), 'error_code' => mb_substr($errorCode, 0, 120)]);
    }

    /** Abandon définitif après les reprises : l'état le dit, il ne se déguise pas en « aucune correspondance ». */
    public function fail(string $id, string $errorCode): void
    {
        MatchingSearch::query()->whereKey($id)->whereIn('state', [MatchingSearch::QUEUED, MatchingSearch::RUNNING])
            ->update(['state' => MatchingSearch::FAILED, 'error_code' => mb_substr($errorCode, 0, 120), 'finished_at' => now()]);
    }

    /** Résultat devenu sans objet (publication retirée, personne indisponible) : la recherche s'arrête, ses tranches suivantes aussi. */
    public function cancel(string $id, string $reason): void
    {
        MatchingSearch::query()->whereKey($id)->whereIn('state', [MatchingSearch::QUEUED, MatchingSearch::RUNNING])
            ->update(['state' => MatchingSearch::CANCELLED, 'cancel_reason' => mb_substr($reason, 0, 24), 'finished_at' => now()]);
    }

    /** Annule les recherches ouvertes d'un sujet (appel immédiat des retraits). */
    public function cancelFor(string $kind, string $subjectId, string $reason): void
    {
        MatchingSearch::query()->where($kind === self::FOR_PUBLICATION ? 'publication_id' : 'user_id', $subjectId)
            ->whereIn('state', [MatchingSearch::QUEUED, MatchingSearch::RUNNING])
            ->update(['state' => MatchingSearch::CANCELLED, 'cancel_reason' => mb_substr($reason, 0, 24), 'finished_at' => now()]);
    }
}
