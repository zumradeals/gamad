<?php

namespace App\Services;

use App\Enums\ProjectHostingState;
use App\Enums\ProjectStage;
use App\Enums\ProjectState;
use App\Models\City;
use App\Models\Domain;
use App\Models\Project;
use App\Models\ProjectStateChange;
use App\Models\User;
use App\Models\Zumra;
use App\Support\Normalizer;
use App\Support\SkillList;
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
     * Crée un projet privé dont `$actor` est le porteur (P1). Dans une ZUMRA dont il est membre, l'accueil est immédiat ; dans une
     * autre, c'est une demande d'accueil : le projet reste un brouillon privé, rattaché seulement une fois l'accueil accepté.
     *
     * @param  array{name?: ?string, goal?: ?string, domain_id?: ?string, hosting_message?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function create(User $actor, Zumra $zumra, array $data): Project
    {
        Gate::forUser($actor)->authorize('create', [Project::class, $zumra]);

        [$name, $goal] = $this->fields($data);
        $domainId = $this->domainId($data['domain_id'] ?? null, $zumra);
        $message = $this->hostingMessage($data['hosting_message'] ?? null);

        $this->throttle($actor, $zumra);

        try {
            return DB::transaction(function () use ($actor, $zumra, $name, $goal, $domainId, $message) {
                if ($this->nameTaken($zumra, $name)) {
                    throw $this->duplicate();
                }

                $member = $zumra->roleOf($actor) !== null;

                $project = new Project(['name' => $name, 'goal' => $goal, 'domain_id' => $domainId]);
                $project->forceFill([
                    'owner_id' => $actor->getKey(),
                    'state' => ProjectState::Idea,
                    'state_changed_at' => now(),
                    'stage' => ProjectStage::Idea,
                    'hosting_state' => $member ? ProjectHostingState::Accepted : ProjectHostingState::Pending,
                    'hosting_message' => $member ? null : $message,
                    'hosting_requested_at' => $member ? null : now(),
                    'hosting_decided_at' => $member ? now() : null,
                    'hosting_decided_by' => $member ? $actor->getKey() : null,
                    'origin_zumra_id' => $member ? $zumra->getKey() : null,
                ]);
                $project->zumra()->associate($zumra);
                $project->save();

                $this->record($project, null, ProjectState::Idea, $actor);

                if (! $member) {
                    app(ProjectHosting::class)->notifyManagers($project, $actor);
                }

                return $project;
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicate();
        }
    }

    /**
     * Modifie le nom, le but, le domaine et la localité. L'état change par `changeState()`, l'étape par `setStage()`.
     *
     * @param  array{name?: ?string, goal?: ?string, domain_id?: ?string, city_id?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function update(User $actor, Project $project, array $data): void
    {
        Gate::forUser($actor)->authorize('update', $project);

        [$name, $goal] = $this->fields($data);
        $attributes = ['name' => $name, 'goal' => $goal];

        if (array_key_exists('domain_id', $data)) {
            $attributes['domain_id'] = $this->domainId($data['domain_id'], $project->zumra, keep: $project->domain_id);
        }

        if (array_key_exists('city_id', $data)) {
            $attributes['city_id'] = $this->cityId($data['city_id'], keep: $project->city_id);
        }

        try {
            DB::transaction(function () use ($project, $attributes, $name) {
                if ($this->nameTaken($project->zumra, $name, $project)) {
                    throw $this->duplicate();
                }

                $project->update($attributes);
            });
        } catch (UniqueConstraintViolationException) {
            throw $this->duplicate();
        }
    }

    /**
     * Contrôle le nom et le but sans rien créer : l'écran de création les remonte avec les autres erreurs, en même temps.
     *
     * @param  array{name?: ?string, goal?: ?string}  $data
     *
     * @throws ValidationException
     */
    public function check(array $data): void
    {
        $this->fields($data);
    }

    /** L'étape de vie du projet, de l'idée à l'activité : le porteur seul, dans les deux sens. */
    public function setStage(User $actor, Project $project, ProjectStage $stage): void
    {
        Gate::forUser($actor)->authorize('update', $project);

        $project->forceFill(['stage' => $stage])->save();
    }

    /**
     * Les compétences recherchées par le projet, dans le référentiel commun (jamais plus de huit).
     *
     * @param  array<int, string>  $names
     *
     * @throws ValidationException
     */
    public function setNeeds(User $actor, Project $project, array $names): void
    {
        Gate::forUser($actor)->authorize('update', $project);

        $project->syncSkills(SkillList::clean($names, 'needs', Project::MAX_SKILLS, 'Un projet recherche au plus '.Project::MAX_SKILLS.' compétences.'));
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

    /** Domaine actif de la liste de référence ; à défaut, celui de la ZUMRA d'accueil. */
    private function domainId(mixed $id, Zumra $zumra, ?string $keep = null): ?string
    {
        if (blank($id)) {
            return $keep ?? $zumra->domain_id;
        }

        $found = Domain::query()->whereKey($id)->where(fn ($q) => $q->where('active', true)->when($keep, fn ($k) => $k->orWhere('id', $keep)))->value('id');

        return $found ?? throw ValidationException::withMessages(['domain_id' => 'Choisissez un domaine dans la liste.']);
    }

    private function cityId(mixed $id, ?string $keep = null): ?string
    {
        if (blank($id)) {
            return null;
        }

        return City::query()->whereKey($id)->where(fn ($q) => $q->where('active', true)->when($keep, fn ($k) => $k->orWhere('id', $keep)))->value('id')
            ?? throw ValidationException::withMessages(['city_id' => 'Choisissez une ville dans la liste.']);
    }

    private function hostingMessage(?string $message): ?string
    {
        $message = Normalizer::multiline((string) $message);

        if (mb_strlen($message) > 280) {
            throw ValidationException::withMessages(['hosting_message' => 'Le mot à la ZUMRA ne peut pas dépasser 280 caractères.']);
        }

        return $message === '' ? null : $message;
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
    private function throttle(User $user, Zumra $zumra): void
    {
        $key = "project-create:{$user->getKey()}";

        // Outil avancé d'une ZUMRA engagée (D-166) : plafond de créations relevé.
        $max = (int) config('gamad.limits.projects_per_day') * (app(Contributions::class)->isEngaged($zumra) ? (int) config('gamad.engaged_limit_multiplier') : 1);

        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw ValidationException::withMessages(['name' => 'Vous avez atteint la limite de créations de projets pour aujourd’hui.']);
        }

        RateLimiter::hit($key, 86400);
    }
}
