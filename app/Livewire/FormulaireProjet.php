<?php

namespace App\Livewire;

use App\Livewire\Concerns\GuardsProjectsModule;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Zumra;
use App\Services\Projects;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Créer un projet en quelques instants (P1) : son nom, ce qu'on veut réaliser, son domaine, la ZUMRA qui l'accompagnera. Le porteur
 * est la personne connectée ; le projet commence privé. Une ZUMRA dont on n'est pas membre est une demande d'accueil. Modifier un projet
 * ne change que ce qui lui appartient : nom, but, domaine, localité (réservé au porteur).
 */
#[Layout('components.layouts.app', ['narrow' => true])]
class FormulaireProjet extends Component
{
    use GuardsProjectsModule;

    #[Locked]
    public ?string $projectId = null;

    public string $name = '';

    public string $goal = '';

    public string $domain_id = '';

    public string $city_id = '';

    public string $zumra_id = '';

    public string $hosting_message = '';

    public function mount(?Zumra $zumra = null, ?Project $project = null): void
    {
        if ($project?->exists) {
            Gate::authorize('update', $project);

            $this->projectId = $project->id;
            $this->name = $project->name;
            $this->goal = $project->goal;
            $this->domain_id = (string) ($project->domain_id ?? $project->zumra->domain_id);
            $this->city_id = (string) $project->city_id;

            return;
        }

        abort_unless(Auth::user()?->hasVerifiedEmail(), 403, 'Vérifiez votre adresse e-mail pour créer un projet.');
        $this->zumra_id = $zumra?->exists ? $zumra->id : (string) (Auth::user()->zumraMemberships()->orderBy('joined_at')->value('zumra_id') ?? '');
    }

    public function save(Projects $projects)
    {
        // Les erreurs d'un envoi précédent ne survivent pas à un nouvel envoi.
        $this->resetErrorBag();

        if ($this->projectId) {
            $project = $this->project();
            $projects->update(Auth::user(), $project, ['name' => $this->name, 'goal' => $this->goal, 'domain_id' => $this->domain_id, 'city_id' => $this->city_id]);
            session()->flash('status', 'Projet mis à jour.');

            return $this->redirect(route('projets.show', $project), navigate: false);
        }

        $zumra = Str::isUuid($this->zumra_id) ? Zumra::query()->find($this->zumra_id) : null;

        // Toutes les erreurs remontent ensemble, chacune sous son champ.
        $errors = [];

        try {
            $projects->check(['name' => $this->name, 'goal' => $this->goal]);
        } catch (ValidationException $e) {
            $errors = $e->errors();
        }

        if (! $zumra) {
            $errors['zumra_id'] = ['Choisissez la ZUMRA qui accompagnera votre projet.'];
        }

        if ($this->domain_id === '') {
            $errors['domain_id'] = ['Choisissez un domaine dans la liste.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $project = $projects->create(Auth::user(), $zumra, ['name' => $this->name, 'goal' => $this->goal, 'domain_id' => $this->domain_id, 'hosting_message' => $this->hosting_message]);
        session()->flash('status', $project->isHosted() ? 'Projet créé. Il est privé : vous seul le voyez.' : 'Projet créé et gardé privé. La demande d’accueil est envoyée à la ZUMRA.');

        return $this->redirect(route('projets.show', $project), navigate: false);
    }

    public function render()
    {
        $project = $this->projectId ? $this->project() : null;
        $user = Auth::user();
        $mine = $user->zumraMemberships()->pluck('zumra_id')->all();

        return view('livewire.formulaire-projet', [
            'editing' => $project !== null,
            'projectModel' => $project,
            'domains' => Domain::query()->where(fn (Builder $q) => $q->where('active', true)->when($project?->domain_id, fn ($k, $id) => $k->orWhere('id', $id)))->ordered()->get(),
            'zumras' => $project ? collect() : Zumra::query()->orderBy('name_key')->limit(300)->get(['id', 'name']),
            'mine' => $mine,
            'nameMax' => Project::NAME_MAX,
            'goalMax' => Project::GOAL_MAX,
        ])->title($project ? 'Modifier le projet' : 'Créer un projet');
    }

    private function project(): Project
    {
        $project = Project::query()->with('zumra')->findOrFail($this->projectId);

        Gate::authorize('update', $project);

        return $project;
    }
}
