<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Models\Publication;
use App\Models\Report;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Modération minimale (D-089) : masquer une publication, la rétablir, classer des signalements sans suite, et l'explication
 * de l'auteur. Chaque acte de modération est inscrit au journal d'administration (CLAUDE.md, règle 8) dans la même
 * transaction que l'acte : pas d'acte sans trace, pas de trace sans acte.
 */
class Moderation
{
    public const REASON_MAX = 500;

    public const EXPLANATION_MAX = 500;

    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Masque une publication, avec un motif que son auteur verra, et clôt ses signalements ouverts.
     *
     * @throws ValidationException
     */
    public function hide(User $actor, Publication $publication, ?string $reason): void
    {
        Gate::forUser($actor)->authorize('moderate');

        $reason = $this->reason($reason, 'Indiquez le motif : l’auteur le verra.');

        DB::transaction(function () use ($actor, $publication, $reason) {
            $locked = $this->lock($publication);

            $this->refuseOwn($actor, $locked);

            if ($locked->isHidden()) {
                throw ValidationException::withMessages(['reason' => 'Cette publication est déjà masquée.']);
            }

            $reports = Report::query()->where('publication_id', $locked->getKey())->open();
            $count = (clone $reports)->count();
            $reports->update(['resolution' => Report::HIDDEN, 'resolved_by' => $actor->getKey(), 'resolved_at' => now()]);

            $locked->forceFill(['hidden_at' => now(), 'hidden_by' => $actor->getKey(), 'hidden_reason' => $reason])->save();

            AdminJournal::record($actor, 'publication.hidden', $locked, $reason, ['author_id' => $locked->author_id, 'reports_closed' => $count]);

            $this->notifier->push($locked->author, NotificationKind::PublicationHidden, $actor, $locked);
            $publication->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** Rétablit une publication masquée ; la raison du rétablissement est facultative mais tracée si elle est donnée. */
    public function restore(User $actor, Publication $publication, ?string $reason = null): void
    {
        Gate::forUser($actor)->authorize('moderate');

        $reason = Normalizer::multiline((string) $reason);

        if (mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => 'Le motif ne peut pas dépasser '.self::REASON_MAX.' caractères.']);
        }

        DB::transaction(function () use ($actor, $publication, $reason) {
            $locked = $this->lock($publication);

            $this->refuseOwn($actor, $locked);

            if (! $locked->isHidden()) {
                throw ValidationException::withMessages(['reason' => 'Cette publication n’est pas masquée.']);
            }

            $locked->forceFill(['hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null, 'author_explanation' => null, 'explained_at' => null])->save();

            AdminJournal::record($actor, 'publication.restored', $locked, $reason === '' ? null : $reason, ['author_id' => $locked->author_id]);

            $this->notifier->push($locked->author, NotificationKind::PublicationRestored, $actor, $locked);
            $publication->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Classe sans suite les signalements ouverts d'une publication (elle reste visible).
     *
     * @throws ValidationException
     */
    public function dismiss(User $actor, Publication $publication, ?string $reason = null): void
    {
        Gate::forUser($actor)->authorize('moderate');

        $reason = Normalizer::multiline((string) $reason);

        DB::transaction(function () use ($actor, $publication, $reason) {
            $locked = $this->lock($publication);

            $reports = Report::query()->where('publication_id', $locked->getKey())->open();
            $count = (clone $reports)->count();

            if ($count === 0) {
                throw ValidationException::withMessages(['reason' => 'Aucun signalement à classer.']);
            }

            $reports->update(['resolution' => Report::DISMISSED, 'resolved_by' => $actor->getKey(), 'resolved_at' => now()]);

            AdminJournal::record($actor, 'report.dismissed', $locked, $reason === '' ? null : mb_substr($reason, 0, self::REASON_MAX), ['author_id' => $locked->author_id, 'reports_closed' => $count]);
        });
    }

    /**
     * L'auteur d'une publication masquée écrit (ou réécrit) son explication, tant que la publication est masquée.
     *
     * @throws ValidationException
     */
    public function explain(User $author, Publication $publication, ?string $text): void
    {
        $text = Normalizer::multiline((string) $text);

        if ($text === '') {
            throw ValidationException::withMessages(['explanation' => 'Écrivez votre explication.']);
        }

        if (mb_strlen($text) > self::EXPLANATION_MAX) {
            throw ValidationException::withMessages(['explanation' => 'Une explication ne peut pas dépasser '.self::EXPLANATION_MAX.' caractères.']);
        }

        DB::transaction(function () use ($author, $publication, $text) {
            $locked = $this->lock($publication);

            Gate::forUser($author)->authorize('explain', $locked);

            $locked->forceFill(['author_explanation' => $text, 'explained_at' => now()])->save();

            $this->notifier->pushToModeration(NotificationKind::PublicationExplained, $author, $locked);
            $publication->setRawAttributes($locked->getAttributes(), true);
        });
    }

    private function lock(Publication $publication): Publication
    {
        return Publication::query()->whereKey($publication->getKey())->lockForUpdate()->firstOrFail();
    }

    /** On ne modère pas sa propre publication : un autre modérateur ou le super administrateur le fait. */
    private function refuseOwn(User $actor, Publication $publication): void
    {
        if ($actor->getKey() === $publication->author_id) {
            throw ValidationException::withMessages(['reason' => 'Vous ne pouvez pas modérer votre propre publication : un autre modérateur doit le faire.']);
        }
    }

    private function reason(?string $reason, string $message): string
    {
        $reason = Normalizer::multiline((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => $message]);
        }

        if (mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => 'Le motif ne peut pas dépasser '.self::REASON_MAX.' caractères.']);
        }

        return $reason;
    }
}
