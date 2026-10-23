<?php

namespace App\Livewire;

use App\Enums\ProjectState;
use App\Enums\ProjectStepState;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\Publication;
use App\Services\Projects;
use App\Services\ProjectSteps;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Monde d'un projet : la page publique d'un projet, lisible sans compte (D-005). Onglet Avancement : son état, ses étapes et
 * l'histoire de ses changements d'état ; onglet Fil : les avancées et réalisations publiées en son nom (Lot 4b). Le fondateur et
 * les responsables de la ZUMRA le modifient, changent son état, gèrent ses étapes et publient en son nom (D-127).
 */
#[Layout('components.layouts.app')]
class MondeProjet extends Component
{
    public const TABS = ['avancement', 'fil'];

    public const PAGE_SIZE = 10;

    public const MAX_SIZE = 100;

    #[Locked]
    public string $projectId;

    #[Url(as: 'onglet', except: 'avancement')]
    public string $tab = 'avancement';

    public int $limit = self::PAGE_SIZE;

    /** Le titre de la nouvelle étape en cours d'écriture. */
    public string $stepTitle = '';

    public function mount(Project $project): void
    {
        $this->projectId = $project->id;
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, self::TABS, true) ? $tab : 'avancement';
        $this->limit = self::PAGE_SIZE;
    }

    public function more(): void
    {
        $this->limit = min($this->limit + self::PAGE_SIZE, self::MAX_SIZE);
    }

    public function changeState(string $state, Projects $projects): void
    {
        $projects->changeState(Auth::user(), $this->project(), ProjectState::tryFrom($state) ?? abort(404));

        session()->flash('status', 'État du projet mis à jour.');
    }

    public function addStep(ProjectSteps $steps): void
    {
        $steps->add(Auth::user(), $this->project(), $this->stepTitle);

        $this->reset('stepTitle');
    }

    public function setStepState(string $id, string $state, ProjectSteps $steps): void
    {
        $steps->setState(Auth::user(), $this->step($id), ProjectStepState::tryFrom($state) ?? abort(404));
    }

    public function moveStep(string $id, int $direction, ProjectSteps $steps): void
    {
        $steps->move(Auth::user(), $this->step($id), $direction);
    }

    public function removeStep(string $id, ProjectSteps $steps): void
    {
        $steps->remove(Auth::user(), $this->step($id));
    }

    public function render()
    {
        $project = Project::query()->with(['zumra.domain', 'zumra.city'])->findOrFail($this->projectId);

        Gate::authorize('view', $project);

        $viewer = Auth::user();
        $tab = in_array($this->tab, self::TABS, true) ? $this->tab : 'avancement';
        $manages = $viewer && Gate::forUser($viewer)->allows('update', $project);

        $steps = $project->steps()->get();

        // Les avancées et réalisations que la personne a le droit de voir, de la plus récente à la plus ancienne.
        $publications = collect();
        $hasMore = false;

        if ($tab === 'fil') {
            $found = Publication::query()->visibleTo($viewer)->where('project_id', $project->id)
                ->with(['author.profile', 'domain', 'city', 'skills', 'zumra', 'project'])
                ->orderByDesc('created_at')->orderByDesc('id')
                ->limit($this->limit + 1)->get();

            $hasMore = $found->count() > $this->limit && $this->limit < self::MAX_SIZE;
            $publications = $found->take($this->limit);
        }

        return view('livewire.monde-projet', [
            'project' => $project,
            'viewer' => $viewer,
            'tab' => $tab,
            'manages' => $manages,
            'steps' => $steps,
            'stepsDone' => $steps->where('state', ProjectStepState::Done)->count(),
            'changes' => $tab === 'avancement' ? $project->stateChanges()->with('user')->orderBy('created_at')->orderBy('id')->get() : collect(),
            'publications' => $publications,
            'hasMore' => $hasMore,
            'stepMax' => ProjectStep::MAX_PER_PROJECT,
            'managed' => $viewer ? $viewer->managedZumraIds() : [],
            'memberOf' => $viewer ? $viewer->zumraMemberships()->pluck('zumra_id')->all() : [],
            'gestures' => collect(),
        ])->title($project->name);
    }

    private function project(): Project
    {
        return Project::query()->with('zumra')->findOrFail($this->projectId);
    }

    /** Une étape de ce projet : un identifiant qui n'en est pas une, ou d'un autre projet, n'existe pas. */
    private function step(string $id): ProjectStep
    {
        abort_unless(Str::isUuid($id), 404);

        return ProjectStep::query()->where('project_id', $this->projectId)->with('project.zumra')->find($id) ?? abort(404);
    }
}
