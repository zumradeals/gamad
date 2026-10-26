<?php

namespace App\Services;

use App\Enums\JoinRequestState;
use App\Enums\NotificationKind;
use App\Models\ParticipationRequest;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * L'équipe d'un projet (D-128, P1) : des collaborateurs qui ont donné leur accord, par invitation du porteur (ProjectInvitations) ou sur
 * leur demande acceptée par lui (ParticipationRequests). Ils n'ont pas besoin d'être membres de la ZUMRA d'accueil.
 */
class ProjectTeam
{
    public function __construct(private readonly Notifier $notifier) {}

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

    /** Retire une personne de l'équipe : le porteur ; la personne en est informée. */
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

    private function closeRequest(Project $project, User $person, JoinRequestState $state, User $actor): void
    {
        ParticipationRequest::query()->where('project_id', $project->getKey())->where('user_id', $person->getKey())
            ->where('state', JoinRequestState::Pending->value)
            ->update(['state' => $state->value, 'decided_by' => $actor->getKey(), 'decided_at' => now()]);
    }
}
