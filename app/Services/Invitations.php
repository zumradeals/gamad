<?php

namespace App\Services;

use App\Enums\InvitationState;
use App\Enums\JoinRequestState;
use App\Enums\NotificationKind;
use App\Models\Invitation;
use App\Models\JoinRequest;
use App\Models\User;
use App\Models\Zumra;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Les invitations à rejoindre une ZUMRA (D-110) : un responsable invite une personne trouvée par capacité, avec un mot facultatif ;
 * la personne accepte ou refuse ; l'invitation expire après 14 jours. Une seule invitation en attente par personne et par ZUMRA.
 */
class Invitations
{
    public function __construct(private readonly Memberships $memberships, private readonly Notifier $notifier) {}

    /**
     * @throws ValidationException
     */
    public function invite(User $actor, Zumra $zumra, User $invitee, ?string $message = null): Invitation
    {
        Gate::forUser($actor)->authorize('invite', $zumra);

        $message = Normalizer::multiline((string) $message);

        if (mb_strlen($message) > Invitation::MESSAGE_MAX) {
            throw ValidationException::withMessages(['inviteWord' => 'Un mot ne peut pas dépasser '.Invitation::MESSAGE_MAX.' caractères.']);
        }

        // On n'invite que des personnes qui existent pour les autres (adresse vérifiée) et dont le profil est visible pour soi.
        $profile = $invitee->profile;
        $profile?->setRelation('user', $invitee);

        if (! $invitee->hasVerifiedEmail() || ! $profile || ! Gate::forUser($actor)->allows('view', $profile)) {
            throw ValidationException::withMessages(['inviteWord' => 'Cette personne ne peut pas être invitée.']);
        }

        $this->throttle($actor);

        return DB::transaction(function () use ($actor, $zumra, $invitee, $message) {
            $locked = Zumra::query()->whereKey($zumra->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('invite', $locked);

            if ($locked->roleOf($invitee)) {
                throw ValidationException::withMessages(['inviteWord' => 'Cette personne est déjà dans la ZUMRA.']);
            }

            if (JoinRequest::query()->where('zumra_id', $locked->getKey())->where('user_id', $invitee->getKey())->where('state', JoinRequestState::Pending->value)->exists()) {
                throw ValidationException::withMessages(['inviteWord' => 'Cette personne a déjà demandé à rejoindre : acceptez sa demande.']);
            }

            // Une invitation expirée est close avant d'en faire une nouvelle (l'index n'admet qu'une invitation en attente).
            Invitation::query()->where('zumra_id', $locked->getKey())->where('user_id', $invitee->getKey())
                ->where('state', InvitationState::Pending->value)->where('expires_at', '<=', now())
                ->update(['state' => InvitationState::Expired->value]);

            if (Invitation::query()->where('zumra_id', $locked->getKey())->where('user_id', $invitee->getKey())->open()->exists()) {
                throw ValidationException::withMessages(['inviteWord' => 'Cette personne a déjà une invitation en attente.']);
            }

            $invitation = new Invitation(['message' => $message === '' ? null : $message, 'state' => InvitationState::Pending, 'expires_at' => now()->addDays(Invitation::VALID_DAYS)]);
            $invitation->zumra()->associate($locked);
            $invitation->user()->associate($invitee);
            $invitation->inviter()->associate($actor);
            $invitation->save();

            $this->notifier->push($invitee, NotificationKind::ZumraInvitationReceived, $actor, zumra: $locked);

            return $invitation;
        });
    }

    /** La personne invitée accepte : elle devient membre, et celui qui l'a invitée en est informé. */
    public function accept(User $user, Invitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation) {
            $fresh = $this->mine($user, $invitation);

            $this->memberships->admit($fresh->zumra, $user);

            $this->notifier->push($fresh->inviter, NotificationKind::ZumraInvitationAccepted, $user, zumra: $fresh->zumra);
        });
    }

    /** La personne invitée refuse. */
    public function refuse(User $user, Invitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation) {
            $this->mine($user, $invitation)->update(['state' => InvitationState::Refused, 'decided_at' => now()]);
        });
    }

    /** Un responsable annule une invitation en attente. */
    public function cancel(User $actor, Invitation $invitation): void
    {
        DB::transaction(function () use ($actor, $invitation) {
            $fresh = Invitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('manage', $fresh->zumra);

            if ($fresh->state !== InvitationState::Pending) {
                throw ValidationException::withMessages(['invitation' => 'Cette invitation n’est plus en attente.']);
            }

            $fresh->update(['state' => InvitationState::Cancelled, 'decided_at' => now()]);
        });
    }

    /** L'invitation de cette personne, verrouillée, encore ouverte (en attente et non expirée). */
    private function mine(User $user, Invitation $invitation): Invitation
    {
        $fresh = Invitation::query()->whereKey($invitation->getKey())->lockForUpdate()->firstOrFail();

        abort_unless($fresh->user_id === $user->getKey(), 404);
        Gate::forUser($user)->authorize('respond', $fresh);

        if (! $fresh->isOpen()) {
            throw ValidationException::withMessages(['invitation' => $fresh->state === InvitationState::Pending ? 'Cette invitation a expiré.' : 'Cette invitation n’est plus en attente.']);
        }

        return $fresh;
    }

    /** Limite de débit (D-117) : un nombre d'invitations par jour et par personne qui invite. */
    private function throttle(User $user): void
    {
        $key = "invitation:{$user->getKey()}";

        if (RateLimiter::tooManyAttempts($key, config('gamad.limits.invitations_per_day'))) {
            throw ValidationException::withMessages(['inviteWord' => 'Vous avez atteint la limite d’invitations pour aujourd’hui.']);
        }

        RateLimiter::hit($key, 86400);
    }
}
