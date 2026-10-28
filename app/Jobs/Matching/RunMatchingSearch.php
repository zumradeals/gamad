<?php

namespace App\Jobs\Matching;

use App\Core\Execution\BackgroundRunner;
use App\Core\Execution\Execution;
use App\Core\Execution\Instrumented;
use App\Models\Publication;
use App\Models\User;
use App\Services\Matching;
use App\Services\MatchingSearches;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

/**
 * Une tranche de recherche de correspondances (50 candidats au plus). Quand il en reste, elle confie la suivante à la file au lieu de
 * monopoliser un processus : durée et volume restent bornés, une interruption reprend à la dernière tranche faite.
 * Elle relit les droits à chaque exécution (état courant, jamais l'état au moment de la demande) et s'annule si le sujet n'est plus éligible.
 */
class RunMatchingSearch implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 25;

    /** @var array<string, int> */
    public array $runMetrics = [];

    public function __construct(public string $searchId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new Instrumented, (new WithoutOverlapping($this->searchId))->releaseAfter(15)->expireAfter(60)];
    }

    public function handle(Matching $matching, MatchingSearches $searches): void
    {
        $search = $searches->begin($this->searchId);

        if ($search === null) {
            return;
        }

        try {
            if ($search->publication_id !== null) {
                $publication = Publication::query()->with(['author.profile', 'skills', 'city'])->find($search->publication_id);

                if ($publication === null || ! $matching->isSearchable($publication)) {
                    $searches->cancel($search->getKey(), 'obsolete');

                    return;
                }

                $slice = $matching->searchPublication($publication, $search->cursor, 50, BackgroundRunner::remaining(Matching::SLICE_SECONDS));
                $this->runMetrics = ['produced' => $slice['produced'], 'examined' => $slice['examined'], 'slices' => 1];
                $searches->progress($search, $slice['produced'], $slice['cursor']);

                if ($slice['cursor'] !== null) {
                    Execution::dispatch(new self($this->searchId));

                    return;
                }

                $searches->finish($search->getKey(), 0);

                return;
            }

            $user = User::query()->find($search->user_id);

            if ($user === null || ! $user->isActive() || ! $matching->preferences($user->ensureProfile())['enabled']) {
                $searches->cancel($search->getKey(), 'consent');

                return;
            }

            // Même principe que pour une publication : une tranche bornée en candidats et en temps, puis la suivante reprend au curseur.
            $slice = $matching->searchPerson($user, $search->cursor, 50, BackgroundRunner::remaining(Matching::SLICE_SECONDS));
            $this->runMetrics = ['produced' => $slice['produced'], 'examined' => $slice['examined'], 'slices' => 1];
            $searches->progress($search, $slice['produced'], $slice['cursor']);

            // Les recherches de personnes pour ses propres publications sont des recherches à part entière, elles aussi par tranches.
            foreach ($slice['delegate'] as $publicationId) {
                $searches->request(MatchingSearches::FOR_PUBLICATION, $publicationId);
            }

            if ($slice['cursor'] !== null) {
                Execution::dispatch(new self($this->searchId));

                return;
            }

            $searches->finish($search->getKey(), 0);
        } catch (Throwable $error) {
            $searches->retrying($this->searchId, class_basename($error));

            throw $error;
        }
    }

    public function failed(Throwable $error): void
    {
        app(MatchingSearches::class)->fail($this->searchId, class_basename($error));
    }
}
