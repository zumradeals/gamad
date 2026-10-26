<?php

namespace App\Services;

use App\Enums\InvitationState;
use App\Enums\JoinRequestState;
use App\Enums\NotificationKind;
use App\Enums\ZumraDuty;
use App\Enums\ZumraRole;
use App\Models\Invitation;
use App\Models\JoinRequest;
use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Support\ZumraModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Les membres d'une ZUMRA : admettre, quitter, retirer, nommer et retirer un responsable (D-110, D-111), et passer la fondation
 * quand le fondateur supprime son compte (D-115). Chaque règle est tenue ici, côté serveur, sous verrou de la ZUMRA : deux
 * décisions simultanées ne peuvent pas se contredire.
 */
class Memberships
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Admet une personne dans la ZUMRA, et clôt ce qui l'y menait : sa demande en attente et ses invitations ouvertes.
     * Sans effet si elle en fait déjà partie.
     */
    public function admit(Zumra $zumra, User $user, ?User $by = null): ZumraMembership
    {
        return DB::transaction(function () use ($zumra, $user, $by) {
            $locked = Zumra::query()->whereKey($zumra->getKey())->lockForUpdate()->firstOrFail();

            $existing = ZumraMembership::query()->where('zumra_id', $locked->getKey())->where('user_id', $user->getKey())->first();

            if ($existing) {
                return $existing;
            }

            $membership = new ZumraMembership(['role' => ZumraRole::Member]);
            $membership->zumra()->associate($locked);
            $membership->user()->associate($user);
            $membership->save();

            JoinRequest::query()->where('zumra_id', $locked->getKey())->where('user_id', $user->getKey())
                ->where('state', JoinRequestState::Pending->value)
                ->update(['state' => JoinRequestState::Accepted->value, 'decided_by' => $by?->getKey(), 'decided_at' => now()]);

            Invitation::query()->where('zumra_id', $locked->getKey())->where('user_id', $user->getKey())
                ->where('state', InvitationState::Pending->value)
                ->update(['state' => InvitationState::Accepted->value, 'decided_at' => now()]);

            return $membership;
        });
    }

    /** Quitte la ZUMRA (tout membre sauf le fondateur). */
    public function leave(User $user, Zumra $zumra): void
    {
        DB::transaction(function () use ($user, $zumra) {
            $locked = $this->lock($zumra);

            Gate::forUser($user)->authorize('leave', $locked);

            ZumraMembership::query()->where('zumra_id', $locked->getKey())->where('user_id', $user->getKey())->delete();

            // On sort aussi de l'équipe des projets de la ZUMRA.
            app(ProjectTeam::class)->forgetPerson($locked, $user);
        });
    }

    /** Retire un membre ; il en est informé. */
    public function remove(User $actor, Zumra $zumra, ZumraMembership $target): void
    {
        DB::transaction(function () use ($actor, $zumra, $target) {
            $locked = $this->lock($zumra);
            $fresh = ZumraMembership::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('removeMember', [$locked, $fresh]);

            $person = $fresh->user;
            $fresh->delete();

            app(ProjectTeam::class)->forgetPerson($locked, $person);

            $this->notifier->push($person, NotificationKind::ZumraMemberRemoved, $actor, zumra: $locked);
        });
    }

    /** Nomme un membre responsable : le fondateur seul. */
    public function promote(User $actor, Zumra $zumra, ZumraMembership $target): void
    {
        DB::transaction(function () use ($actor, $zumra, $target) {
            $locked = $this->lock($zumra);
            $fresh = ZumraMembership::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('setManager', [$locked, $fresh]);

            if ($fresh->role === ZumraRole::Manager) {
                return;
            }

            $fresh->update(['role' => ZumraRole::Manager]);

            $this->notifier->push($fresh->user, NotificationKind::ZumraRoleGranted, $actor, zumra: $locked);
        });
    }

    /** Retire à un responsable son rôle : il redevient simple membre. Le fondateur seul. */
    public function demote(User $actor, Zumra $zumra, ZumraMembership $target): void
    {
        DB::transaction(function () use ($actor, $zumra, $target) {
            $locked = $this->lock($zumra);
            $fresh = ZumraMembership::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('setManager', [$locked, $fresh]);

            $fresh->update(['role' => ZumraRole::Member, 'duty' => null, 'duty_state' => null]);
        });
    }

    /**
     * Le fondateur propose un poste de gouvernance à un responsable. Le poste reste « en attente » tant que la personne ne l'a pas
     * accepté : il n'est jamais occupé d'office. Une proposition en attente sur ce poste est remplacée ; un poste déjà accepté doit
     * d'abord être retiré. La personne en est informée. Aucun privilège n'en découle.
     */
    public function proposeDuty(User $actor, Zumra $zumra, ZumraMembership $target, ZumraDuty $duty): void
    {
        DB::transaction(function () use ($actor, $zumra, $target, $duty) {
            $locked = $this->lock($zumra);
            $fresh = ZumraMembership::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('setManager', [$locked, $fresh]);

            if ($fresh->role !== ZumraRole::Manager) {
                throw ValidationException::withMessages(['duty' => 'Un poste se propose à un responsable : nommez d’abord la personne responsable.']);
            }

            $holder = ZumraMembership::query()->where('zumra_id', $locked->getKey())->where('duty', $duty->value)->whereKeyNot($fresh->getKey())->first();

            if ($holder?->dutyEffective()) {
                throw ValidationException::withMessages(['duty' => 'Ce poste est déjà occupé : retirez-le d’abord à la personne qui le tient.']);
            }

            $holder?->update(['duty' => null, 'duty_state' => null]);

            if ($fresh->duty === $duty && $fresh->dutyPending()) {
                return;
            }

            if ($fresh->dutyEffective() && $fresh->duty === $duty) {
                throw ValidationException::withMessages(['duty' => 'Cette personne tient déjà ce poste.']);
            }

            $fresh->update(['duty' => $duty, 'duty_state' => ZumraMembership::DUTY_PENDING]);

            $this->notifier->push($fresh->user, NotificationKind::ZumraDutyProposed, $actor, zumra: $locked);
        });
    }

    /** Le fondateur retire une proposition en attente ou un poste tenu. */
    public function clearDuty(User $actor, Zumra $zumra, ZumraMembership $target): void
    {
        DB::transaction(function () use ($actor, $zumra, $target) {
            $locked = $this->lock($zumra);
            $fresh = ZumraMembership::query()->whereKey($target->getKey())->lockForUpdate()->firstOrFail();

            Gate::forUser($actor)->authorize('setManager', [$locked, $fresh]);

            $fresh->update(['duty' => null, 'duty_state' => null]);
        });
    }

    /** La personne désignée accepte le poste proposé : il devient effectif, et le fondateur en est informé. */
    public function acceptDuty(User $user, ZumraMembership $membership): void
    {
        $this->answerDuty($user, $membership, accept: true);
    }

    /** La personne désignée refuse le poste proposé : il redevient à pourvoir, et le fondateur en est informé. */
    public function declineDuty(User $user, ZumraMembership $membership): void
    {
        $this->answerDuty($user, $membership, accept: false);
    }

    private function answerDuty(User $user, ZumraMembership $membership, bool $accept): void
    {
        DB::transaction(function () use ($user, $membership, $accept) {
            $fresh = ZumraMembership::query()->whereKey($membership->getKey())->lockForUpdate()->firstOrFail();
            $zumra = $this->lock($fresh->zumra);

            // Seule la personne désignée répond ; module fermé, la ZUMRA n'existe pas pour elle.
            abort_unless($fresh->user_id === $user->getKey() && ZumraModule::accessibleTo($user), 404);

            if ($fresh->role !== ZumraRole::Manager || ! $fresh->dutyPending()) {
                throw ValidationException::withMessages(['duty' => 'Cette proposition n’est plus en attente.']);
            }

            $fresh->update($accept ? ['duty_state' => null] : ['duty' => null, 'duty_state' => null]);

            $founder = ZumraMembership::query()->where('zumra_id', $zumra->getKey())->where('role', ZumraRole::Founder->value)->with('user')->first()?->user;

            if ($founder) {
                $this->notifier->push($founder, $accept ? NotificationKind::ZumraDutyAccepted : NotificationKind::ZumraDutyDeclined, $user, zumra: $zumra);
            }
        });
    }

    /**
     * Le fondateur supprime son compte (D-115) : la fondation passe au responsable le plus ancien, à défaut au membre le plus
     * ancien. Sans personne d'autre, la ZUMRA est supprimée avec lui. L'acte est inscrit au journal quand la fondation change de
     * main, et la nouvelle personne fondatrice en est informée.
     */
    public function passFoundationFrom(User $founder): void
    {
        $memberships = ZumraMembership::query()->where('user_id', $founder->getKey())->where('role', ZumraRole::Founder->value)->with('zumra')->get();

        foreach ($memberships as $membership) {
            $zumra = $membership->zumra;

            $successor = ZumraMembership::query()->where('zumra_id', $zumra->getKey())->where('user_id', '!=', $founder->getKey())
                ->orderByRaw("case role when 'manager' then 0 else 1 end")->orderBy('joined_at')->orderBy('id')->first();

            // L'ancien fondateur sort d'abord : la base n'accepte qu'un fondateur par ZUMRA.
            $membership->delete();

            if (! $successor) {
                continue;
            }

            $successor->update(['role' => ZumraRole::Founder, 'duty' => null, 'duty_state' => null]);

            AdminJournal::record($founder, 'zumra.founder_transferred', $zumra, 'Le fondateur a supprimé son compte.', [
                'from' => $founder->getKey(), 'to' => $successor->user_id,
            ]);

            $this->notifier->push($successor->user, NotificationKind::ZumraFounderTransferred, null, zumra: $zumra);
        }
    }

    private function lock(Zumra $zumra): Zumra
    {
        return Zumra::query()->whereKey($zumra->getKey())->lockForUpdate()->firstOrFail();
    }
}
