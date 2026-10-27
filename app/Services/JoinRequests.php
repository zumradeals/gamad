<?php

namespace App\Services;

use App\Enums\JoinRequestState;
use App\Enums\NotificationKind;
use App\Models\JoinRequest;
use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Support\Normalizer;
use App\Support\ZumraModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Les demandes d'adhésion (D-110) : « Je veux rejoindre » avec un mot facultatif de 280 caractères, retirable ; le fondateur ou
 * un responsable l'accepte ou la décline. Une demande par personne et par ZUMRA, vérifiée sous verrou de la ZUMRA.
 */
class JoinRequests
{
    public function __construct(private readonly Memberships $memberships, private readonly Notifier $notifier) {}

    /**
     * @throws ValidationException
     */
    public function propose(User $user, Zumra $zumra, ?string $message = null): JoinRequest
    {
        $message = Normalizer::multiline((string) $message);

        if (mb_strlen($message) > JoinRequest::MESSAGE_MAX) {
            throw ValidationException::withMessages(['joinWord' => 'Un mot ne peut pas dépasser '.JoinRequest::MESSAGE_MAX.' caractères.']);
        }

        Gate::forUser($user)->authorize('join', $zumra);

        $this->throttle($user);

        return DB::transaction(function () use ($user, $zumra, $message) {
            $locked = Zumra::query()->whereKey($zumra->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($user)->authorize('join', $locked);

            if ($locked->joinsFreely()) {
                throw ValidationException::withMessages(['joinWord' => 'Cette ZUMRA est en adhésion libre : rejoignez-la directement.']);
            }

            $request = JoinRequest::query()->where('zumra_id', $locked->getKey())->where('user_id', $user->getKey())->first() ?? new JoinRequest;
            $request->fill(['message' => $message === '' ? null : $message, 'state' => JoinRequestState::Pending, 'decided_by' => null, 'decided_at' => null]);
            $request->zumra()->associate($locked);
            $request->user()->associate($user);
            $request->save();

            // Le fondateur et les responsables sont prévenus.
            ZumraMembership::query()->where('zumra_id', $locked->getKey())->whereIn('role', ['founder', 'manager'])->with('user')->get()
                ->each(fn (ZumraMembership $manager) => $this->notifier->push($manager->user, NotificationKind::ZumraRequestReceived, $user, zumra: $locked));

            return $request;
        });
    }

    /**
     * Rejoint librement une ZUMRA à adhésion libre (D-216) : la personne devient membre tout de suite, et le fondateur et les
     * responsables en sont informés. Une ZUMRA sur approbation passe par une demande (propose).
     *
     * @throws ValidationException
     */
    public function join(User $user, Zumra $zumra): void
    {
        Gate::forUser($user)->authorize('join', $zumra);

        $this->throttle($user);

        DB::transaction(function () use ($user, $zumra) {
            $locked = Zumra::query()->whereKey($zumra->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($user)->authorize('join', $locked);

            if (! $locked->joinsFreely()) {
                throw ValidationException::withMessages(['joinWord' => 'Cette ZUMRA examine chaque demande : envoyez-lui la vôtre.']);
            }

            $this->memberships->admit($locked, $user);

            ZumraMembership::query()->where('zumra_id', $locked->getKey())->whereIn('role', ['founder', 'manager'])->with('user')->get()
                ->each(fn (ZumraMembership $manager) => $this->notifier->push($manager->user, NotificationKind::ZumraMemberJoined, $user, zumra: $locked));
        });
    }

    /** Retire sa demande, tant qu'elle est en attente. */
    public function withdraw(User $user, JoinRequest $request): void
    {
        DB::transaction(function () use ($user, $request) {
            $fresh = JoinRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($fresh->user_id === $user->getKey() && ZumraModule::accessibleTo($user), 404);

            if (! $fresh->isPending()) {
                throw ValidationException::withMessages(['joinWord' => 'Cette demande ne peut plus être retirée.']);
            }

            $fresh->update(['state' => JoinRequestState::Withdrawn]);
        });
    }

    /** Accepte une demande : la personne devient membre et en est informée. */
    public function accept(User $actor, JoinRequest $request): void
    {
        DB::transaction(function () use ($actor, $request) {
            $fresh = JoinRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
            $zumra = $fresh->zumra;

            Gate::forUser($actor)->authorize('manage', $zumra);

            if (! $fresh->isPending()) {
                throw ValidationException::withMessages(['request' => 'Cette demande a déjà été traitée.']);
            }

            $this->memberships->admit($zumra, $fresh->user, $actor);

            $this->notifier->push($fresh->user, NotificationKind::ZumraRequestAccepted, $actor, zumra: $zumra);
        });
    }

    /** Décline une demande en attente. La personne n'est pas prévenue (même règle que pour un geste décliné, D-102). */
    public function decline(User $actor, JoinRequest $request): void
    {
        DB::transaction(function () use ($actor, $request) {
            $fresh = JoinRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('manage', $fresh->zumra);

            if (! $fresh->isPending()) {
                throw ValidationException::withMessages(['request' => 'Cette demande a déjà été traitée.']);
            }

            $fresh->update(['state' => JoinRequestState::Declined, 'decided_by' => $actor->getKey(), 'decided_at' => now()]);
        });
    }

    /** Limite de débit contre le spam (D-117) : un nombre de demandes par jour et par personne. */
    private function throttle(User $user): void
    {
        $key = "join-request:{$user->getKey()}";

        if (RateLimiter::tooManyAttempts($key, config('gamad.limits.join_requests_per_day'))) {
            throw ValidationException::withMessages(['joinWord' => 'Vous avez atteint la limite de demandes pour aujourd’hui.']);
        }

        RateLimiter::hit($key, 86400);
    }
}
