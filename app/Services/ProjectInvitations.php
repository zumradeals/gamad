<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Models\Project;
use App\Models\ProjectInvitation;
use App\Models\ProjectMember;
use App\Models\User;
use App\Support\Normalizer;
use App\Support\ZumraModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Inviter des collaborateurs (P1, D-206) : le porteur propose, la personne accepte ou refuse. Personne n'entre dans l'équipe d'un projet
 * sans son consentement, et elle n'a pas besoin d'être membre de la ZUMRA d'accueil.
 */
class ProjectInvitations
{
    public function __construct(private readonly ProjectTeam $team, private readonly Notifier $notifier) {}

    /** @throws ValidationException */
    public function invite(User $owner, Project $project, User $person, ?string $message = null): ProjectInvitation
    {
        Gate::forUser($owner)->authorize('update', $project);

        $message = Normalizer::multiline((string) $message);

        if (mb_strlen($message) > ProjectInvitation::MESSAGE_MAX) {
            throw ValidationException::withMessages(['inviteWord' => 'Un mot ne peut pas dépasser '.ProjectInvitation::MESSAGE_MAX.' caractères.']);
        }

        // On n'invite que des personnes qui existent pour les autres (adresse vérifiée), visibles de soi et non bloquées.
        $profile = $person->profile;
        $profile?->setRelation('user', $person);

        if ($person->getKey() === $owner->getKey() || ! $person->hasVerifiedEmail() || ! $profile || ! Gate::forUser($owner)->allows('view', $profile)) {
            throw ValidationException::withMessages(['inviteWord' => 'Cette personne ne peut pas être invitée.']);
        }

        $this->throttle($owner);

        return DB::transaction(function () use ($owner, $project, $person, $message) {
            $locked = Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            if (ProjectMember::query()->where('project_id', $locked->getKey())->where('user_id', $person->getKey())->exists()) {
                throw ValidationException::withMessages(['inviteWord' => 'Cette personne collabore déjà au projet.']);
            }

            $invitation = ProjectInvitation::query()->where('project_id', $locked->getKey())->where('user_id', $person->getKey())->first() ?? new ProjectInvitation;

            if ($invitation->exists && $invitation->isPending()) {
                throw ValidationException::withMessages(['inviteWord' => 'Une invitation est déjà en attente pour cette personne.']);
            }

            $invitation->fill(['message' => $message === '' ? null : $message]);
            $invitation->forceFill(['state' => ProjectInvitation::PENDING, 'decided_at' => null, 'invited_by' => $owner->getKey()]);
            $invitation->project()->associate($locked);
            $invitation->user()->associate($person);
            $invitation->save();

            $this->notifier->push($person, NotificationKind::ProjectInvited, $owner, project: $locked);

            return $invitation;
        });
    }

    /** La personne invitée accepte : elle entre dans l'équipe, et le porteur en est informé. */
    public function accept(User $user, ProjectInvitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation) {
            $fresh = $this->mine($user, $invitation);

            $this->team->enter($fresh->project, $user, $fresh->inviter);
            $fresh->forceFill(['state' => ProjectInvitation::ACCEPTED, 'decided_at' => now()])->save();

            $this->notifier->push($fresh->project->owner, NotificationKind::ProjectInviteAccepted, $user, project: $fresh->project);
        });
    }

    /** La personne invitée refuse. Le porteur n'est pas prévenu (même règle qu'un geste décliné, D-102). */
    public function refuse(User $user, ProjectInvitation $invitation): void
    {
        DB::transaction(function () use ($user, $invitation) {
            $this->mine($user, $invitation)->forceFill(['state' => ProjectInvitation::REFUSED, 'decided_at' => now()])->save();
        });
    }

    /** Le porteur annule une invitation en attente. */
    public function cancel(User $owner, ProjectInvitation $invitation): void
    {
        DB::transaction(function () use ($owner, $invitation) {
            $fresh = ProjectInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->with('project')->firstOrFail();

            Gate::forUser($owner)->authorize('update', $fresh->project);

            if (! $fresh->isPending()) {
                throw ValidationException::withMessages(['inviteWord' => 'Cette invitation n’est plus en attente.']);
            }

            $fresh->forceFill(['state' => ProjectInvitation::CANCELLED, 'decided_at' => now()])->save();
        });
    }

    private function mine(User $user, ProjectInvitation $invitation): ProjectInvitation
    {
        $fresh = ProjectInvitation::query()->whereKey($invitation->getKey())->lockForUpdate()->with('project.owner', 'inviter')->firstOrFail();

        abort_unless($fresh->user_id === $user->getKey() && ZumraModule::accessibleTo($user), 404);

        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['invitation' => 'Vérifiez votre adresse e-mail pour répondre.']);
        }

        if (! $fresh->isPending()) {
            throw ValidationException::withMessages(['invitation' => 'Cette invitation n’est plus en attente.']);
        }

        return $fresh;
    }

    /** Limite de débit (D-117) : un nombre d'invitations par jour et par personne qui invite. */
    private function throttle(User $user): void
    {
        $key = "project-invite:{$user->getKey()}";

        if (RateLimiter::tooManyAttempts($key, (int) config('gamad.limits.invitations_per_day'))) {
            throw ValidationException::withMessages(['inviteWord' => 'Vous avez atteint la limite d’invitations pour aujourd’hui.']);
        }

        RateLimiter::hit($key, 86400);
    }
}
