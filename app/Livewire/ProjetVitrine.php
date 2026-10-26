<?php

namespace App\Livewire;

use App\Livewire\Concerns\GuardsZumraModule;
use App\Models\ParticipationRequest;
use App\Models\Project;
use App\Services\ParticipationRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Présentation publique limitée d'un projet (P1, D-206) : une vitrine volontaire. Elle ne montre que ce que le porteur a publié (résumé,
 * « comment contribuer », et, selon ses choix, son nom et les compétences recherchées), avec l'étape du projet, son domaine, sa localité
 * et la ZUMRA d'accueil. Jamais le but détaillé, l'équipe, les étapes, les publications ni les documents. Retirée, elle disparaît aussitôt.
 */
class ProjetVitrine extends Component
{
    use GuardsZumraModule;

    #[Locked]
    public string $projectId;

    public string $participationWord = '';

    public function mount(Project $project): void
    {
        Gate::authorize('viewShowcase', $project);

        $this->projectId = $project->id;
    }

    public function requestParticipation(ParticipationRequests $requests): void
    {
        $this->resetErrorBag();
        $requests->propose(Auth::user(), $this->project(), $this->participationWord);

        $this->reset('participationWord');
        session()->flash('status', 'Demande envoyée. Le porteur du projet va la lire.');
    }

    public function withdrawParticipation(ParticipationRequests $requests): void
    {
        $requests->withdraw(Auth::user(), ParticipationRequest::query()->where('project_id', $this->projectId)->where('user_id', Auth::id())->firstOrFail());

        session()->flash('status', 'Demande retirée.');
    }

    public function render()
    {
        $project = $this->project();
        $viewer = Auth::user();

        abort_unless(Gate::forUser($viewer)->allows('viewShowcase', $project), 404);

        $owner = $project->showcase_show_owner ? $project->owner : null;
        $ownerProfile = $owner?->profile;
        $ownerProfile?->setRelation('user', $owner);

        return view('livewire.projet-vitrine', [
            'project' => $project,
            'viewer' => $viewer,
            'zumra' => $project->zumra,
            'owner' => $owner && $owner->isActive() ? $owner : null,
            'ownerProfile' => $ownerProfile && Gate::forUser($viewer)->allows('view', $ownerProfile) ? $ownerProfile : null,
            'needs' => $project->showcase_show_needs ? $project->neededSkills : collect(),
            'participation' => $viewer ? Gate::forUser($viewer)->inspect('participate', $project) : null,
            'myRequest' => $viewer ? ParticipationRequest::query()->where('project_id', $project->id)->where('user_id', $viewer->getKey())->first() : null,
            'confirmedCompanies' => $project->companyLinks()->where('state', 'confirmed')->with('company')->get()->filter(fn ($link) => $link->company->verified())->values(),
        ])->title($project->name);
    }

    private function project(): Project
    {
        return Project::query()->with(['zumra.domain', 'zumra.city', 'owner.profile', 'domain', 'city'])->findOrFail($this->projectId);
    }
}
