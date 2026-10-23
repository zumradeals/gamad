<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Zumra;
use App\Services\Projects;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Créer un projet dans une ZUMRA, ou modifier son nom et son but (Lot 4a, D-127). Écran sobre, construit avec les composants
 * existants (D-129).
 */
#[Layout('components.layouts.app')]
class FormulaireProjet extends Component
{
    #[Locked]
    public ?string $zumraId = null;

    #[Locked]
    public ?string $projectId = null;

    public string $name = '';

    public string $goal = '';

    public function mount(?Zumra $zumra = null, ?Project $project = null): void
    {
        if ($project?->exists) {
            Gate::authorize('update', $project);

            $this->projectId = $project->id;
            $this->name = $project->name;
            $this->goal = $project->goal;

            return;
        }

        abort_unless($zumra?->exists, 404);
        Gate::authorize('create', [Project::class, $zumra]);

        $this->zumraId = $zumra->id;
    }

    public function save(Projects $projects)
    {
        // Les erreurs d'un envoi précédent ne survivent pas à un nouvel envoi.
        $this->resetErrorBag();

        if ($this->projectId) {
            $project = $this->project();
            $projects->update(Auth::user(), $project, ['name' => $this->name, 'goal' => $this->goal]);
            session()->flash('status', 'Projet mis à jour.');

            return $this->redirect(route('projets.show', $project), navigate: false);
        }

        $project = $projects->create(Auth::user(), $this->zumra(), ['name' => $this->name, 'goal' => $this->goal]);
        session()->flash('status', 'Projet créé. Il commence comme une idée.');

        return $this->redirect(route('projets.show', $project), navigate: false);
    }

    public function render()
    {
        $project = $this->projectId ? $this->project() : null;
        $zumra = $project ? $project->zumra : $this->zumra();

        return view('livewire.formulaire-projet', [
            'editing' => $project !== null,
            'projectModel' => $project,
            'zumra' => $zumra,
            'nameMax' => Project::NAME_MAX,
            'goalMax' => Project::GOAL_MAX,
        ])->title($project ? 'Modifier le projet' : 'Créer un projet');
    }

    private function zumra(): Zumra
    {
        $zumra = Zumra::query()->findOrFail($this->zumraId);

        Gate::authorize('create', [Project::class, $zumra]);

        return $zumra;
    }

    private function project(): Project
    {
        $project = Project::query()->with('zumra')->findOrFail($this->projectId);

        Gate::authorize('update', $project);

        return $project;
    }
}
