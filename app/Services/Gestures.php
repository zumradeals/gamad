<?php

namespace App\Services;

use App\Enums\GestureState;
use App\Enums\NeedState;
use App\Enums\NotificationKind;
use App\Models\Gesture;
use App\Models\Publication;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Envoyer, retirer, accepter et décliner un geste (D-082, D-083). Chaque règle est vérifiée ici, côté serveur, sous verrou
 * de la publication : deux personnes qui répondent en même temps, ou deux décisions simultanées, ne peuvent pas contredire
 * les règles.
 */
class Gestures
{
    public function __construct(private readonly Notifier $notifier, private readonly Conversations $conversations) {}

    /**
     * Envoie un geste. Une personne n'a qu'un geste par publication ; après l'avoir retiré, elle peut en envoyer un nouveau.
     *
     * @throws ValidationException
     */
    public function propose(User $user, Publication $publication, ?string $message = null): Gesture
    {
        $message = Normalizer::multiline((string) $message);

        if (mb_strlen($message) > Gesture::MESSAGE_MAX) {
            throw ValidationException::withMessages(['message' => 'Un mot ne peut pas dépasser '.Gesture::MESSAGE_MAX.' caractères.']);
        }

        $this->throttle($user);

        return DB::transaction(function () use ($user, $publication, $message) {
            // Les gestes d'une même publication s'enchaînent : l'unicité « un geste par personne » ne dépend pas de la chance.
            $locked = Publication::query()->whereKey($publication->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($user)->authorize('gesture', $locked);

            $gesture = Gesture::query()->where('publication_id', $locked->getKey())->where('user_id', $user->getKey())->first();
            $values = ['message' => $message === '' ? null : $message, 'state' => GestureState::Sent, 'decided_at' => null];

            if ($gesture) {
                $gesture->update($values);
            } else {
                $gesture = new Gesture($values);
                $gesture->publication()->associate($locked);
                $gesture->author()->associate($user);
                $gesture->save();
            }

            // L'auteur est prévenu, ou, pour une publication de ZUMRA, son fondateur et ses responsables (D-116).
            $locked->managers()->each(fn (User $manager) => $this->notifier->push($manager, NotificationKind::GestureReceived, $user, $locked));

            return $gesture;
        });
    }

    /** Retire son geste, tant qu'il n'est pas accepté. */
    public function withdraw(User $user, Gesture $gesture): void
    {
        DB::transaction(function () use ($user, $gesture) {
            $this->lockPublicationOf($gesture);
            $fresh = Gesture::query()->whereKey($gesture->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($user)->authorize('withdraw', $fresh);

            $fresh->update(['state' => GestureState::Withdrawn]);
            $gesture->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    /**
     * Accepte un geste. Plusieurs gestes peuvent être acceptés ; le premier fait passer un besoin « ouvert » à « en cours ».
     */
    public function accept(User $actor, Gesture $gesture): void
    {
        DB::transaction(function () use ($actor, $gesture) {
            $publication = $this->lockPublicationOf($gesture);
            $fresh = Gesture::query()->whereKey($gesture->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('decide', $fresh->setRelation('publication', $publication));

            $fresh->update(['state' => GestureState::Accepted, 'decided_at' => now()]);

            if ($publication->isNeed() && $publication->need_state === NeedState::Open) {
                $publication->update(['need_state' => NeedState::InProgress]);
            }

            // Accepter ouvre la conversation privée entre les deux personnes (PRODUIT §4) et prévient la personne acceptée.
            $this->conversations->open($fresh->setRelation('publication', $publication), $actor);

            $gesture->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    /** Décline un geste en attente. */
    public function decline(User $actor, Gesture $gesture): void
    {
        DB::transaction(function () use ($actor, $gesture) {
            $publication = $this->lockPublicationOf($gesture);
            $fresh = Gesture::query()->whereKey($gesture->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('decide', $fresh->setRelation('publication', $publication));

            $fresh->update(['state' => GestureState::Declined, 'decided_at' => now()]);
            $gesture->setRawAttributes($fresh->getAttributes(), true);
        });
    }

    private function lockPublicationOf(Gesture $gesture): Publication
    {
        return Publication::query()->whereKey($gesture->publication_id)->lockForUpdate()->firstOrFail();
    }

    /** Limites de débit contre le spam (D-086) : par heure et par jour, par personne. */
    private function throttle(User $user): void
    {
        $limits = [
            [config('gamad.limits.gestures_per_hour'), 3600, 'Vous répondez trop vite. Réessayez dans un moment.'],
            [config('gamad.limits.gestures_per_day'), 86400, 'Vous avez atteint la limite de réponses pour aujourd’hui.'],
        ];

        foreach ($limits as $index => [$max, , $message]) {
            if (RateLimiter::tooManyAttempts("gesture:{$user->getKey()}:$index", $max)) {
                throw ValidationException::withMessages(['message' => $message]);
            }
        }

        foreach ($limits as $index => [, $window]) {
            RateLimiter::hit("gesture:{$user->getKey()}:$index", $window);
        }
    }
}
