<?php

namespace App\Services;

use App\Enums\JoinRequestState;
use App\Enums\NotificationKind;
use App\Models\ParticipationRequest;
use App\Models\Project;
use App\Models\User;
use App\Models\ZumraMembership;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Les demandes « Je veux participer » (D-128) : un membre de la ZUMRA demande à entrer dans l'équipe d'un projet, avec un mot
 * facultatif de 280 caractères, retirable ; le fondateur ou un responsable de la ZUMRA l'accepte ou la décline.
 */
class ParticipationRequests
{
    public function __construct(private readonly ProjectTeam $team, private readonly Notifier $notifier) {}

    /**
     * @throws ValidationException
     */
    public function propose(User $user, Project $project, ?string $message = null): ParticipationRequest
    {
        $message = Normalizer::multiline((string) $message);

        if (mb_strlen($message) > ParticipationRequest::MESSAGE_MAX) {
            throw ValidationException::withMessages(['participationWord' => 'Un mot ne peut pas dépasser '.ParticipationRequest::MESSAGE_MAX.' caractères.']);
        }

        Gate::forUser($user)->authorize('participate', $project);

        $this->throttle($user);

        return DB::transaction(function () use ($user, $project, $message) {
            $locked = Project::query()->whereKey($project->getKey())->lockForUpdate()->with('zumra')->firstOrFail();

            Gate::forUser($user)->authorize('participate', $locked);

            $request = ParticipationRequest::query()->where('project_id', $locked->getKey())->where('user_id', $user->getKey())->first() ?? new ParticipationRequest;
            $request->fill(['message' => $message === '' ? null : $message, 'state' => JoinRequestState::Pending, 'decided_by' => null, 'decided_at' => null]);
            $request->project()->associate($locked);
            $request->user()->associate($user);
            $request->save();

            // Le fondateur et les responsables de la ZUMRA sont prévenus.
            ZumraMembership::query()->where('zumra_id', $locked->zumra_id)->whereIn('role', ['founder', 'manager'])->with('user')->get()
                ->each(fn (ZumraMembership $manager) => $this->notifier->push($manager->user, NotificationKind::ProjectParticipationRequested, $user, project: $locked));

            return $request;
        });
    }

    /** Retire sa demande, tant qu'elle est en attente. */
    public function withdraw(User $user, ParticipationRequest $request): void
    {
        DB::transaction(function () use ($user, $request) {
            $fresh = ParticipationRequest::query()->whereKey($request->getKey())->lockForUpdate()->firstOrFail();

            abort_unless($fresh->user_id === $user->getKey(), 404);

            if (! $fresh->isPending()) {
                throw ValidationException::withMessages(['participationWord' => 'Cette demande ne peut plus être retirée.']);
            }

            $fresh->update(['state' => JoinRequestState::Withdrawn]);
        });
    }

    /**
     * Accepte une demande : la personne entre dans l'équipe et en est informée. Si elle a quitté la ZUMRA depuis, la demande est
     * refusée.
     *
     * @throws ValidationException
     */
    public function accept(User $actor, ParticipationRequest $request): void
    {
        DB::transaction(function () use ($actor, $request) {
            $fresh = ParticipationRequest::query()->whereKey($request->getKey())->lockForUpdate()->with('project.zumra', 'user')->firstOrFail();
            $project = $fresh->project;

            Gate::forUser($actor)->authorize('update', $project);

            if (! $fresh->isPending()) {
                throw ValidationException::withMessages(['request' => 'Cette demande a déjà été traitée.']);
            }

            if (! $project->zumra->roleOf($fresh->user)) {
                throw ValidationException::withMessages(['request' => 'Cette personne n’est plus dans la ZUMRA.']);
            }

            $this->team->enter($project, $fresh->user, null);
            $fresh->update(['state' => JoinRequestState::Accepted, 'decided_by' => $actor->getKey(), 'decided_at' => now()]);

            $this->notifier->push($fresh->user, NotificationKind::ProjectParticipationAccepted, $actor, project: $project);
        });
    }

    /** Décline une demande en attente. La personne n'est pas prévenue (même règle qu'un geste décliné, D-102). */
    public function decline(User $actor, ParticipationRequest $request): void
    {
        DB::transaction(function () use ($actor, $request) {
            $fresh = ParticipationRequest::query()->whereKey($request->getKey())->lockForUpdate()->with('project.zumra')->firstOrFail();

            Gate::forUser($actor)->authorize('update', $fresh->project);

            if (! $fresh->isPending()) {
                throw ValidationException::withMessages(['request' => 'Cette demande a déjà été traitée.']);
            }

            $fresh->update(['state' => JoinRequestState::Declined, 'decided_by' => $actor->getKey(), 'decided_at' => now()]);
        });
    }

    /** Limite de débit contre le spam (D-142) : un nombre de demandes par jour et par personne. */
    private function throttle(User $user): void
    {
        $key = "participation-request:{$user->getKey()}";

        if (RateLimiter::tooManyAttempts($key, config('gamad.limits.participation_requests_per_day'))) {
            throw ValidationException::withMessages(['participationWord' => 'Vous avez atteint la limite de demandes pour aujourd’hui.']);
        }

        RateLimiter::hit($key, 86400);
    }
}
