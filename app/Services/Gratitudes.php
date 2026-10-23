<?php

namespace App\Services;

use App\Enums\GestureState;
use App\Enums\NotificationKind;
use App\Models\Gesture;
use App\Models\Gratitude;
use App\Models\Publication;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Remercier la personne qui a aidé (D-084). Le remerciement est public sur son profil ; elle peut le masquer. Il n'y en a
 * qu'un par besoin, il ne se compte pas et ne donne aucune note.
 */
class Gratitudes
{
    public function __construct(private readonly Publications $publications, private readonly Notifier $notifier) {}

    /**
     * « Marquer résolu et remercier » : le besoin passe à « résolu » et le remerciement est enregistré, ensemble ou pas du tout.
     *
     * @throws ValidationException
     */
    public function resolveAndThank(User $actor, Publication $publication, string $helperId, ?string $text): Gratitude
    {
        return DB::transaction(function () use ($actor, $publication, $helperId, $text) {
            $this->publications->resolve($actor, $publication);

            return $this->thank($actor, $publication, $helperId, $text);
        });
    }

    /**
     * Remercie une personne dont un geste a été accepté sur ce besoin résolu.
     *
     * @throws ValidationException
     */
    public function thank(User $actor, Publication $publication, string $helperId, ?string $text): Gratitude
    {
        $body = Normalizer::multiline((string) $text);

        if ($body === '') {
            throw ValidationException::withMessages(['gratitudeText' => 'Écrivez votre remerciement.']);
        }

        if (mb_strlen($body) > Gratitude::BODY_MAX) {
            throw ValidationException::withMessages(['gratitudeText' => 'Un remerciement ne peut pas dépasser '.Gratitude::BODY_MAX.' caractères.']);
        }

        return DB::transaction(function () use ($actor, $publication, $helperId, $body) {
            $locked = Publication::query()->whereKey($publication->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('thank', $locked);

            $accepted = Str::isUuid($helperId)
                ? Gesture::query()->where('publication_id', $locked->getKey())->where('user_id', $helperId)->where('state', GestureState::Accepted->value)->exists()
                : false;

            if (! $accepted) {
                throw ValidationException::withMessages(['helper' => 'Choisissez une personne dont vous avez accepté la proposition.']);
            }

            $gratitude = new Gratitude(['body' => $body]);
            $gratitude->publication()->associate($locked);
            $gratitude->author()->associate($actor);
            $gratitude->helper()->associate(User::query()->findOrFail($helperId));
            $gratitude->save();

            $this->notifier->push($gratitude->helper, NotificationKind::GratitudeReceived, $actor, $locked);

            return $gratitude;
        });
    }

    /** Masque le remerciement du profil de la personne remerciée (elle seule), ou le rétablit. */
    public function setHidden(User $actor, Gratitude $gratitude, bool $hidden): void
    {
        Gate::forUser($actor)->authorize('hide', $gratitude);

        $gratitude->update(['hidden_at' => $hidden ? now() : null]);
    }
}
