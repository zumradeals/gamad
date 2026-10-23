<?php

namespace App\Livewire;

use App\Enums\ProjectState;
use App\Models\Project;
use App\Services\Projects;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Monde d'un projet (Lot 4a) : la page publique d'un projet, lisible sans compte (D-005) : son nom, sa ZUMRA, son état, son but
 * et l'histoire de ses changements d'état. Le fondateur et les responsables de la ZUMRA le modifient et changent son état (D-127).
 */
#[Layout('components.layouts.app')]
class MondeProjet extends Component
{
    #[Locked]
    public string $projectId;

    public function mount(Project $project): void
    {
        $this->projectId = $project->id;
    }

    public function changeState(string $state, Projects $projects): void
    {
        $projects->changeState(Auth::user(), $this->project(), ProjectState::tryFrom($state) ?? abort(404));

        session()->flash('status', 'État du projet mis à jour.');
    }

    public function render()
    {
        $project = Project::query()->with(['zumra.domain', 'zumra.city'])->findOrFail($this->projectId);

        Gate::authorize('view', $project);

        $viewer = Auth::user();

        return view('livewire.monde-projet', [
            'project' => $project,
            'viewer' => $viewer,
            'manages' => $viewer && Gate::forUser($viewer)->allows('update', $project),
            'changes' => $project->stateChanges()->with('user')->orderBy('created_at')->orderBy('id')->get(),
        ])->title($project->name);
    }

    private function project(): Project
    {
        return Project::query()->with('zumra')->findOrFail($this->projectId);
    }
}
