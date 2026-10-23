<?php

namespace App\Services;

use App\Enums\ProjectStepState;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Les étapes d'un projet (D-134) : ajouter, renommer, changer l'état, déplacer, retirer. Seuls le fondateur et les responsables de
 * la ZUMRA du projet les gèrent (D-127). L'ordre est celui de `position`, sans trou après un retrait.
 */
class ProjectSteps
{
    /**
     * @throws ValidationException
     */
    public function add(User $actor, Project $project, ?string $title): ProjectStep
    {
        Gate::forUser($actor)->authorize('update', $project);

        $title = $this->title($title);

        return DB::transaction(function () use ($project, $title) {
            // Le projet est verrouillé : deux ajouts simultanés ne prennent pas la même position.
            Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            $count = ProjectStep::query()->where('project_id', $project->getKey())->count();

            if ($count >= ProjectStep::MAX_PER_PROJECT) {
                throw ValidationException::withMessages(['stepTitle' => 'Un projet a '.ProjectStep::MAX_PER_PROJECT.' étapes au plus.']);
            }

            $step = new ProjectStep(['title' => $title]);
            $step->forceFill(['state' => ProjectStepState::Todo, 'state_changed_at' => now(), 'position' => $count + 1]);
            $step->project()->associate($project);
            $step->save();

            return $step;
        });
    }

    /**
     * @throws ValidationException
     */
    public function rename(User $actor, ProjectStep $step, ?string $title): void
    {
        Gate::forUser($actor)->authorize('update', $step->project);

        $step->update(['title' => $this->title($title)]);
    }

    public function setState(User $actor, ProjectStep $step, ProjectStepState $state): void
    {
        Gate::forUser($actor)->authorize('update', $step->project);

        if ($step->state === $state) {
            return;
        }

        $step->forceFill(['state' => $state, 'state_changed_at' => now()])->save();
    }

    /** Monte (-1) ou descend (+1) une étape d'un cran ; sans effet aux extrémités. */
    public function move(User $actor, ProjectStep $step, int $direction): void
    {
        Gate::forUser($actor)->authorize('update', $step->project);

        DB::transaction(function () use ($step, $direction) {
            Project::query()->whereKey($step->project_id)->lockForUpdate()->firstOrFail();

            $current = ProjectStep::query()->findOrFail($step->getKey());
            $other = ProjectStep::query()->where('project_id', $current->project_id)
                ->where('position', $current->position + ($direction < 0 ? -1 : 1))->first();

            if (! $other) {
                return;
            }

            // La contrainte d'unicité interdit deux positions égales : on passe par une position libre.
            $a = $current->position;
            $b = $other->position;
            $current->forceFill(['position' => 0])->save();
            $other->forceFill(['position' => $a])->save();
            $current->forceFill(['position' => $b])->save();
        });
    }

    public function remove(User $actor, ProjectStep $step): void
    {
        Gate::forUser($actor)->authorize('update', $step->project);

        DB::transaction(function () use ($step) {
            Project::query()->whereKey($step->project_id)->lockForUpdate()->firstOrFail();

            $step = ProjectStep::query()->findOrFail($step->getKey());
            $step->delete();

            // Les positions restent consécutives.
            ProjectStep::query()->where('project_id', $step->project_id)->where('position', '>', $step->position)->orderBy('position')->get()
                ->each(fn (ProjectStep $later) => $later->forceFill(['position' => $later->position - 1])->save());
        });
    }

    private function title(?string $title): string
    {
        $title = Normalizer::display((string) $title);

        if (mb_strlen($title) < ProjectStep::TITLE_MIN || mb_strlen($title) > ProjectStep::TITLE_MAX) {
            throw ValidationException::withMessages(['stepTitle' => 'Le nom d’une étape doit avoir entre '.ProjectStep::TITLE_MIN.' et '.ProjectStep::TITLE_MAX.' caractères.']);
        }

        return $title;
    }
}
