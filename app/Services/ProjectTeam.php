<?php

namespace App\Services;

use App\Enums\JoinRequestState;
use App\Enums\NotificationKind;
use App\Models\ParticipationRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\Zumra;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * L'équipe d'un projet (D-128) : composée de membres de la ZUMRA, ajoutés par le fondateur et les responsables (D-127), admis sur
 * demande (voir ParticipationRequests), ou entrés d'eux-mêmes pour les responsables. On y reste tant qu'on est dans la ZUMRA.
 */
class ProjectTeam
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Ajoute à l'équipe un membre vérifié de la ZUMRA du projet ; il en est informé, et une demande en attente est close.
     *
     * @throws ValidationException
     */
    public function add(User $actor, Project $project, User $person): ProjectMember
    {
        Gate::forUser($actor)->authorize('update', $project);

        return DB::transaction(function () use ($actor, $project, $person) {
            $locked = Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            if (! $person->hasVerifiedEmail() || ! $locked->zumra->roleOf($person)) {
                throw ValidationException::withMessages(['team' => 'Seuls les membres de la ZUMRA entrent dans l’équipe d’un projet.']);
            }

            if (ProjectMember::query()->where('project_id', $locked->getKey())->where('user_id', $person->getKey())->exists()) {
                throw ValidationException::withMessages(['team' => 'Cette personne est déjà dans l’équipe.']);
            }

            $member = $this->enter($locked, $person, $actor);

            $this->closeRequest($locked, $person, JoinRequestState::Accepted, $actor);

            $this->notifier->push($person, NotificationKind::ProjectMemberAdded, $actor, project: $locked);

            return $member;
        });
    }

    /** Enregistre l'entrée d'une personne dans l'équipe (sans règle ni notification : les appelants les portent). */
    public function enter(Project $project, User $person, ?User $by): ProjectMember
    {
        $member = new ProjectMember;
        $member->forceFill(['joined_at' => now()]);
        $member->project()->associate($project);
        $member->user()->associate($person);
        $member->added_by = $by?->getKey();
        $member->save();

        return $member;
    }

    /** Retire une personne de l'équipe : le fondateur et les responsables ; la personne en est informée. */
    public function remove(User $actor, ProjectMember $member): void
    {
        DB::transaction(function () use ($actor, $member) {
            $fresh = ProjectMember::query()->whereKey($member->getKey())->lockForUpdate()->with('project.zumra')->firstOrFail();

            Gate::forUser($actor)->authorize('update', $fresh->project);

            $person = $fresh->user;
            $project = $fresh->project;
            $fresh->delete();

            $this->notifier->push($person, NotificationKind::ProjectMemberRemoved, $actor, project: $project);
        });
    }

    /** Quitte l'équipe : la personne seule, sans que personne ne soit prévenu. */
    public function leave(User $user, Project $project): void
    {
        $deleted = ProjectMember::query()->where('project_id', $project->getKey())->where('user_id', $user->getKey())->delete();

        abort_if($deleted === 0, 404);
    }

    /** Une personne quitte une ZUMRA, ou en est retirée : elle sort aussi de l'équipe de ses projets, et ses demandes en attente sont closes. */
    public function forgetPerson(Zumra $zumra, User $person): void
    {
        $projects = Project::query()->where('zumra_id', $zumra->getKey())->select('id');

        ProjectMember::query()->whereIn('project_id', $projects)->where('user_id', $person->getKey())->delete();

        ParticipationRequest::query()->whereIn('project_id', $projects)->where('user_id', $person->getKey())
            ->where('state', JoinRequestState::Pending->value)->update(['state' => JoinRequestState::Withdrawn->value]);
    }

    private function closeRequest(Project $project, User $person, JoinRequestState $state, User $actor): void
    {
        ParticipationRequest::query()->where('project_id', $project->getKey())->where('user_id', $person->getKey())
            ->where('state', JoinRequestState::Pending->value)
            ->update(['state' => $state->value, 'decided_by' => $actor->getKey(), 'decided_at' => now()]);
    }
}
