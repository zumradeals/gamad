<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Models\ModerationNotice;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Avis, suspension et rétablissement d'une personne (D-145, D-151). La suspension est précédée d'un avis de la modération : la
 * personne dispose d'un délai pour s'expliquer, et la modération ne peut suspendre qu'une fois l'explication reçue ou le délai
 * écoulé (PRODUIT §6, règle 6). Chaque acte est inscrit au journal d'administration dans la même transaction que l'acte.
 */
class PersonModeration
{
    public const REASON_MAX = 500;

    public const EXPLANATION_MAX = 1000;

    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Envoie un avis à une personne : elle verra le motif et pourra s'expliquer.
     *
     * @throws ValidationException
     */
    public function issueNotice(User $actor, User $person, ?string $reason): ModerationNotice
    {
        Gate::forUser($actor)->authorize('sanction-person', $person);

        $reason = $this->reason($reason, 'Indiquez le motif de l’avis : la personne le verra.');

        return DB::transaction(function () use ($actor, $person, $reason) {
            $locked = $this->lock($person);

            if ($locked->isSuspended()) {
                throw ValidationException::withMessages(['reason' => 'Cette personne est déjà suspendue.']);
            }

            if ($locked->openNotice()) {
                throw ValidationException::withMessages(['reason' => 'Un avis est déjà en cours pour cette personne.']);
            }

            $notice = new ModerationNotice(['reason' => $reason]);
            $notice->user()->associate($locked);
            $notice->issuer()->associate($actor);
            $notice->save();

            AdminJournal::record($actor, 'person.notice_issued', $locked, $reason, ['notice_id' => $notice->getKey(), 'delai_jours' => (int) config('gamad.moderation.explanation_days')]);

            $this->notifier->push($locked, NotificationKind::ModerationNoticeIssued, $actor);

            return $notice;
        });
    }

    /**
     * La personne visée écrit (ou réécrit) son explication, tant que l'avis est ouvert.
     *
     * @throws ValidationException
     */
    public function explain(User $person, ?string $text): void
    {
        $text = Normalizer::multiline((string) $text);

        if ($text === '') {
            throw ValidationException::withMessages(['explanation' => 'Écrivez votre explication.']);
        }

        if (mb_strlen($text) > self::EXPLANATION_MAX) {
            throw ValidationException::withMessages(['explanation' => 'Une explication ne peut pas dépasser '.self::EXPLANATION_MAX.' caractères.']);
        }

        DB::transaction(function () use ($person, $text) {
            $locked = $this->lock($person);
            $notice = $locked->moderationNotices()->open()->lockForUpdate()->first();

            if (! $notice) {
                throw ValidationException::withMessages(['explanation' => 'Aucun avis de la modération n’attend votre explication.']);
            }

            $notice->forceFill(['explanation' => $text, 'explained_at' => now()])->save();

            $this->notifier->pushToModeration(NotificationKind::ModerationNoticeExplained, $locked);
        });
    }

    /** Classe l'avis sans suite : la personne n'est pas sanctionnée. */
    public function closeNotice(User $actor, ModerationNotice $notice, ?string $reason = null): void
    {
        Gate::forUser($actor)->authorize('moderate');

        $reason = $this->optionalReason($reason);

        DB::transaction(function () use ($actor, $notice, $reason) {
            $locked = ModerationNotice::query()->whereKey($notice->getKey())->lockForUpdate()->firstOrFail();
            $person = $locked->user;

            Gate::forUser($actor)->authorize('sanction-person', $person);

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['reason' => 'Cet avis est déjà clos.']);
            }

            $locked->forceFill(['resolution' => ModerationNotice::CLOSED, 'resolved_by' => $actor->getKey(), 'resolved_at' => now()])->save();

            AdminJournal::record($actor, 'person.notice_closed', $person, $reason, ['notice_id' => $locked->getKey(), 'expliquee' => $locked->isExplained()]);

            $this->notifier->push($person, NotificationKind::ModerationNoticeClosed, $actor);
        });
    }

    /**
     * Suspend une personne, après son avis : explication reçue, ou délai écoulé sans réponse. Le motif est vu par la personne.
     *
     * @throws ValidationException
     */
    public function suspend(User $actor, ModerationNotice $notice, ?string $reason): void
    {
        Gate::forUser($actor)->authorize('moderate');

        $reason = $this->reason($reason, 'Indiquez le motif de la suspension : la personne le verra.');

        DB::transaction(function () use ($actor, $notice, $reason) {
            $locked = ModerationNotice::query()->whereKey($notice->getKey())->lockForUpdate()->firstOrFail();
            $person = $this->lock($locked->user);

            Gate::forUser($actor)->authorize('sanction-person', $person);

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['reason' => 'Cet avis est déjà clos.']);
            }

            if (! $locked->readyForDecision()) {
                $until = $locked->deadline()->copy()->locale('fr')->timezone('Africa/Abidjan')->translatedFormat('j F Y, H:i');

                throw ValidationException::withMessages(['reason' => "La personne a jusqu’au {$until} pour s’expliquer. Vous pourrez suspendre ensuite, ou dès qu’elle aura répondu."]);
            }

            $locked->forceFill(['resolution' => ModerationNotice::SUSPENDED, 'resolved_by' => $actor->getKey(), 'resolved_at' => now()])->save();
            $person->forceFill(['suspended_at' => now(), 'suspended_by' => $actor->getKey(), 'suspension_reason' => $reason])->save();

            AdminJournal::record($actor, 'person.suspended', $person, $reason, ['notice_id' => $locked->getKey(), 'expliquee' => $locked->isExplained()]);
        });
    }

    /**
     * Rétablit une personne suspendue ; la raison est facultative mais tracée si elle est donnée.
     *
     * @throws ValidationException
     */
    public function reinstate(User $actor, User $person, ?string $reason = null): void
    {
        Gate::forUser($actor)->authorize('sanction-person', $person);

        $reason = $this->optionalReason($reason);

        DB::transaction(function () use ($actor, $person, $reason) {
            $locked = $this->lock($person);

            if (! $locked->isSuspended()) {
                throw ValidationException::withMessages(['reason' => 'Cette personne n’est pas suspendue.']);
            }

            $locked->forceFill(['suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null])->save();

            AdminJournal::record($actor, 'person.reinstated', $locked, $reason);

            $this->notifier->push($locked, NotificationKind::PersonReinstated, $actor);
        });
    }

    private function lock(User $person): User
    {
        return User::query()->whereKey($person->getKey())->lockForUpdate()->firstOrFail();
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

    private function optionalReason(?string $reason): ?string
    {
        $reason = Normalizer::multiline((string) $reason);

        if (mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => 'Le motif ne peut pas dépasser '.self::REASON_MAX.' caractères.']);
        }

        return $reason === '' ? null : $reason;
    }
}
