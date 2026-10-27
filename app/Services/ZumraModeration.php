<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Enums\ReportCategory;
use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Models\ZumraPost;
use App\Models\ZumraReport;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Modération des ZUMRA (D-215) : signaler une ZUMRA ou un message de sa Discussion ; suspendre ou rétablir une ZUMRA, masquer ou
 * rétablir un message. Le signalant n'est jamais montré. Chaque décision de la modération exige un motif et est inscrite au journal
 * d'administration. La modération ne lit que les messages signalés ou masqués : la Discussion reste privée.
 */
class ZumraModeration
{
    public function __construct(private readonly Notifier $notifier) {}

    /** @throws ValidationException */
    public function reportZumra(User $reporter, Zumra $zumra, ?string $category, ?string $details = null): ZumraReport
    {
        Gate::forUser($reporter)->authorize('report', $zumra);

        return $this->submit($reporter, $zumra, null, $category, $details);
    }

    /** @throws ValidationException */
    public function reportPost(User $reporter, ZumraPost $post, ?string $category, ?string $details = null): ZumraReport
    {
        Gate::forUser($reporter)->authorize('reportPost', [$post->zumra, $post]);

        return $this->submit($reporter, $post->zumra, $post, $category, $details);
    }

    public function suspend(User $moderator, Zumra $zumra, ?string $reason): void
    {
        Gate::forUser($moderator)->authorize('moderate');
        $reason = $this->reason($reason, true);

        DB::transaction(function () use ($moderator, $zumra, $reason) {
            $locked = Zumra::query()->lockForUpdate()->findOrFail($zumra->getKey());
            $this->refuseOwn($moderator, $locked);
            abort_if($locked->isSuspended(), 404);

            $locked->forceFill(['suspended_at' => now(), 'suspended_by' => $moderator->getKey(), 'suspension_reason' => $reason])->save();
            $this->resolve(ZumraReport::query()->where('zumra_id', $locked->getKey())->whereNull('zumra_post_id'), 'suspended', $moderator);
            AdminJournal::record($moderator, 'zumra.suspended', $locked, $reason);
            $this->notifyManagers($locked, NotificationKind::ZumraSuspended, $moderator);
            $zumra->setRawAttributes($locked->getAttributes(), true);
        });
    }

    public function reinstate(User $moderator, Zumra $zumra, ?string $reason = null): void
    {
        Gate::forUser($moderator)->authorize('moderate');
        $reason = $this->reason($reason, false);

        DB::transaction(function () use ($moderator, $zumra, $reason) {
            $locked = Zumra::query()->lockForUpdate()->findOrFail($zumra->getKey());
            abort_unless($locked->isSuspended(), 404);

            $locked->forceFill(['suspended_at' => null, 'suspended_by' => null, 'suspension_reason' => null])->save();
            AdminJournal::record($moderator, 'zumra.reinstated', $locked, $reason === '' ? null : $reason);
            $this->notifyManagers($locked, NotificationKind::ZumraReinstated, $moderator);
            $zumra->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** Classe sans suite les signalements en attente d'une ZUMRA (sans toucher à ses messages). */
    public function dismissZumra(User $moderator, Zumra $zumra, ?string $reason = null): void
    {
        Gate::forUser($moderator)->authorize('moderate');
        $reason = $this->reason($reason, false);

        DB::transaction(function () use ($moderator, $zumra, $reason) {
            $reports = ZumraReport::query()->where('zumra_id', $zumra->getKey())->whereNull('zumra_post_id')->open();
            if (! $reports->exists()) {
                throw ValidationException::withMessages(['reason' => 'Aucun signalement en attente.']);
            }
            $this->resolve($reports, 'dismissed', $moderator);
            AdminJournal::record($moderator, 'zumra.report.dismissed', $zumra, $reason === '' ? null : $reason);
        });
    }

    public function hidePost(User $moderator, ZumraPost $post, ?string $reason): void
    {
        Gate::forUser($moderator)->authorize('moderate');
        $reason = $this->reason($reason, true);

        DB::transaction(function () use ($moderator, $post, $reason) {
            $locked = ZumraPost::query()->lockForUpdate()->findOrFail($post->getKey());
            if ($locked->user_id === $moderator->getKey()) {
                throw ValidationException::withMessages(['reason' => 'Un autre modérateur doit examiner votre message.']);
            }
            abort_if($locked->hidden_at, 404);

            $locked->forceFill(['hidden_at' => now(), 'hidden_by' => $moderator->getKey(), 'hidden_reason' => $reason])->save();
            $this->resolve(ZumraReport::query()->where('zumra_post_id', $locked->getKey()), 'hidden', $moderator);
            AdminJournal::record($moderator, 'zumra.post.hidden', $locked, $reason, ['zumra_id' => $locked->zumra_id, 'author_id' => $locked->user_id]);
            $this->notifier->push($locked->author, NotificationKind::ZumraMessageHidden, $moderator, zumra: $locked->zumra);
        });
    }

    public function restorePost(User $moderator, ZumraPost $post, ?string $reason = null): void
    {
        Gate::forUser($moderator)->authorize('moderate');
        $reason = $this->reason($reason, false);

        DB::transaction(function () use ($moderator, $post, $reason) {
            $locked = ZumraPost::query()->lockForUpdate()->findOrFail($post->getKey());
            abort_unless($locked->hidden_at, 404);

            $locked->forceFill(['hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null])->save();
            AdminJournal::record($moderator, 'zumra.post.restored', $locked, $reason === '' ? null : $reason, ['zumra_id' => $locked->zumra_id]);
        });
    }

    public function dismissPost(User $moderator, ZumraPost $post, ?string $reason = null): void
    {
        Gate::forUser($moderator)->authorize('moderate');
        $reason = $this->reason($reason, false);

        DB::transaction(function () use ($moderator, $post, $reason) {
            $reports = ZumraReport::query()->where('zumra_post_id', $post->getKey())->open();
            if (! $reports->exists()) {
                throw ValidationException::withMessages(['reason' => 'Aucun signalement en attente.']);
            }
            $this->resolve($reports, 'dismissed', $moderator);
            AdminJournal::record($moderator, 'zumra.post.report.dismissed', $post, $reason === '' ? null : $reason, ['zumra_id' => $post->zumra_id]);
        });
    }

    private function submit(User $reporter, Zumra $zumra, ?ZumraPost $post, ?string $category, ?string $details): ZumraReport
    {
        $reason = ReportCategory::tryFrom((string) $category);

        if (! $reason) {
            throw ValidationException::withMessages(['category' => 'Choisissez la raison du signalement.']);
        }

        $details = Normalizer::multiline((string) $details);

        if (mb_strlen($details) > ZumraReport::DETAILS_MAX) {
            throw ValidationException::withMessages(['details' => 'La précision ne peut pas dépasser '.ZumraReport::DETAILS_MAX.' caractères.']);
        }

        $already = fn () => ValidationException::withMessages(['category' => 'Vous avez déjà envoyé ce signalement. Un modérateur l’examine.']);

        $existing = ZumraReport::query()->where('zumra_id', $zumra->getKey())->where('reporter_id', $reporter->getKey())
            ->when($post, fn ($q) => $q->where('zumra_post_id', $post->getKey()), fn ($q) => $q->whereNull('zumra_post_id'));
        if ($existing->exists()) {
            throw $already();
        }

        $this->throttle($reporter);

        $report = new ZumraReport(['category' => $reason, 'details' => $details === '' ? null : $details]);
        $report->zumra()->associate($zumra);
        $report->post()->associate($post);
        $report->reporter()->associate($reporter);

        try {
            $report->save();
        } catch (UniqueConstraintViolationException) {
            throw $already();
        }

        return $report;
    }

    /** @param  Builder<ZumraReport>  $reports */
    private function resolve($reports, string $resolution, User $moderator): void
    {
        $reports->open()->update(['resolution' => $resolution, 'resolved_by' => $moderator->getKey(), 'resolved_at' => now()]);
    }

    private function reason(?string $reason, bool $required): string
    {
        $reason = Normalizer::multiline((string) $reason);

        if (($required && $reason === '') || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Indiquez un motif de 1 à 500 caractères.']);
        }

        return $reason;
    }

    /** Une personne ne modère pas la ZUMRA qu'elle a fondée ou qu'elle gère : un autre modérateur la reprend. */
    private function refuseOwn(User $moderator, Zumra $zumra): void
    {
        if ($zumra->roleOf($moderator)?->manages()) {
            throw ValidationException::withMessages(['reason' => 'Un autre modérateur doit examiner une ZUMRA que vous gérez.']);
        }
    }

    private function notifyManagers(Zumra $zumra, NotificationKind $kind, User $actor): void
    {
        ZumraMembership::query()->where('zumra_id', $zumra->getKey())->whereIn('role', ['founder', 'manager'])->with('user')->get()
            ->each(fn (ZumraMembership $manager) => $this->notifier->push($manager->user, $kind, $actor, zumra: $zumra));
    }

    private function throttle(User $user): void
    {
        $limits = [
            [config('gamad.limits.reports_per_hour'), 3600, 'Vous signalez trop vite. Réessayez dans un moment.'],
            [config('gamad.limits.reports_per_day'), 86400, 'Vous avez atteint la limite de signalements pour aujourd’hui.'],
        ];

        foreach ($limits as $index => [$max, , $message]) {
            if (RateLimiter::tooManyAttempts("report:{$user->getKey()}:$index", $max)) {
                throw ValidationException::withMessages(['category' => $message]);
            }
        }

        foreach ($limits as $index => [, $window]) {
            RateLimiter::hit("report:{$user->getKey()}:$index", $window);
        }
    }
}
