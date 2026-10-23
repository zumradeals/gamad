<?php

namespace App\Services;

use App\Enums\ProjectState;
use App\Models\Project;
use App\Models\ProjectStateChange;
use App\Models\User;
use App\Models\Zumra;
use App\Support\Normalizer;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Créer, modifier un projet et changer son état (D-127). Toutes les règles sont tenues ici, côté serveur : autorisation
 * (ProjectPolicy), nom unique dans la ZUMRA, but de 500 caractères au plus, transitions d'état permises, limite de création.
 */
class Projects
{
    /**
     * @param  array{name?: ?string, goal?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function create(User $actor, Zumra $zumra, array $data): Project
    {
        Gate::forUser($actor)->authorize('create', [Project::class, $zumra]);

        [$name, $goal] = $this->fields($data);

        $this->throttle($actor);

        try {
            return DB::transaction(function () use ($actor, $zumra, $name, $goal) {
                if ($this->nameTaken($zumra, $name)) {
                    throw $this->duplicate();
                }

                $project = new Project(['name' => $name, 'goal' => $goal]);
                $project->forceFill(['state' => ProjectState::Idea, 'state_changed_at' => now()]);
                $project->zumra()->associate($zumra);
                $project->save();

                $this->record($project, null, ProjectState::Idea, $actor);

                return $project;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicate();
        }
    }

    /**
     * Modifie le nom et le but. L'état change par `changeState()`.
     *
     * @param  array{name?: ?string, goal?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function update(User $actor, Project $project, array $data): void
    {
        Gate::forUser($actor)->authorize('update', $project);

        [$name, $goal] = $this->fields($data);

        try {
            DB::transaction(function () use ($project, $name, $goal) {
                if ($this->nameTaken($project->zumra, $name, $project)) {
                    throw $this->duplicate();
                }

                $project->update(['name' => $name, 'goal' => $goal]);
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicate();
        }
    }

    /**
     * Change l'état, parmi ceux que permet l'état actuel, et garde la trace de l'acte.
     *
     * @throws ValidationException
     */
    public function changeState(User $actor, Project $project, ProjectState $to): void
    {
        Gate::forUser($actor)->authorize('update', $project);

        DB::transaction(function () use ($actor, $project, $to) {
            $locked = Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            if (! in_array($to, $locked->state->next(), true)) {
                throw ValidationException::withMessages(['state' => 'Ce projet ne peut pas passer de « '.$locked->state->label().' » à « '.$to->label().' ».']);
            }

            $from = $locked->state;
            $locked->forceFill(['state' => $to, 'state_changed_at' => now()])->save();
            $this->record($locked, $from, $to, $actor);

            $project->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /**
     * Le nom et le but, contrôlés ensemble : les deux erreurs remontent en même temps, chacune sous son champ.
     *
     * @param  array{name?: ?string, goal?: ?string}  $data
     * @return array{0: string, 1: string}
     *
     * @throws ValidationException
     */
    private function fields(array $data): array
    {
        $errors = [];
        $name = $goal = '';

        try {
            $name = $this->name($data['name'] ?? null);
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        try {
            $goal = $this->goal($data['goal'] ?? null);
        } catch (ValidationException $e) {
            $errors = [...$errors, ...$e->errors()];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [$name, $goal];
    }

    private function record(Project $project, ?ProjectState $from, ProjectState $to, User $actor): void
    {
        $change = new ProjectStateChange(['from_state' => $from, 'to_state' => $to]);
        $change->project()->associate($project);
        $change->user()->associate($actor);
        $change->save();
    }

    private function nameTaken(Zumra $zumra, string $name, ?Project $except = null): bool
    {
        return Project::query()->where('zumra_id', $zumra->getKey())->where('name_key', Normalizer::key($name))
            ->when($except, fn ($query) => $query->whereKeyNot($except->getKey()))->exists();
    }

    private function duplicate(): ValidationException
    {
        return ValidationException::withMessages(['name' => 'Cette ZUMRA a déjà un projet de ce nom.']);
    }

    private function name(?string $name): string
    {
        $name = Normalizer::display((string) $name);

        if (mb_strlen($name) < Project::NAME_MIN || mb_strlen($name) > Project::NAME_MAX) {
            throw ValidationException::withMessages(['name' => 'Le nom doit avoir entre '.Project::NAME_MIN.' et '.Project::NAME_MAX.' caractères.']);
        }

        return $name;
    }

    private function goal(?string $goal): string
    {
        $goal = Normalizer::multiline((string) $goal);

        if ($goal === '') {
            throw ValidationException::withMessages(['goal' => 'Écrivez le but du projet.']);
        }

        if (mb_strlen($goal) > Project::GOAL_MAX) {
            throw ValidationException::withMessages(['goal' => 'Le but ne peut pas dépasser '.Project::GOAL_MAX.' caractères.']);
        }

        return $goal;
    }

    /** Limite de débit contre le spam (D-130) : un nombre de créations par jour et par personne. */
    private function throttle(User $user): void
    {
        $key = "project-create:{$user->getKey()}";

        if (RateLimiter::tooManyAttempts($key, config('gamad.limits.projects_per_day'))) {
            throw ValidationException::withMessages(['name' => 'Vous avez atteint la limite de créations de projets pour aujourd’hui.']);
        }

        RateLimiter::hit($key, 86400);
    }
}
