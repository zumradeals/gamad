<?php

namespace App\Livewire;

use App\Enums\JoinRequestState;
use App\Livewire\Concerns\GuardsProjectsModule;
use App\Models\ParticipationRequest;
use App\Models\Project;
use App\Models\ProjectCompanyLink;
use App\Models\ProjectInvitation;
use App\Services\ProjectCompanyLinks;
use App\Services\ProjectInvitations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * « Mes projets » (P1, D-206), depuis Mon espace : les projets que je porte, ceux où je collabore, mes invitations à accepter ou refuser,
 * mes demandes d'aide en attente et, pour le propriétaire d'une entreprise, les liens à confirmer. Aucun projet d'autrui n'y figure
 * sans mon accord ; aucun Carrefour des projets.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Mes projets')]
class MesProjets extends Component
{
    use GuardsProjectsModule;

    public function accept(string $id, ProjectInvitations $invitations): void
    {
        $invitations->accept(Auth::user(), $this->invitation($id));
        session()->flash('status', 'Bienvenue dans l’équipe du projet.');
    }

    public function refuse(string $id, ProjectInvitations $invitations): void
    {
        $invitations->refuse(Auth::user(), $this->invitation($id));
        session()->flash('status', 'Invitation refusée.');
    }

    public function confirmLink(string $id, ProjectCompanyLinks $links): void
    {
        $links->confirm(Auth::user(), $this->link($id));
        session()->flash('status', 'Lien confirmé.');
    }

    public function declineLink(string $id, ProjectCompanyLinks $links): void
    {
        $links->decline(Auth::user(), $this->link($id));
        session()->flash('status', 'Lien refusé.');
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.mes-projets', [
            'owned' => Project::query()->where('owner_id', $user->getKey())->with(['zumra', 'members'])->orderByDesc('updated_at')->get(),
            'collaborations' => Project::query()->whereHas('members', fn (Builder $members) => $members->where('user_id', $user->getKey()))->with(['zumra', 'owner'])->orderBy('name_key')->get(),
            'invitations' => ProjectInvitation::query()->where('user_id', $user->getKey())->where('state', ProjectInvitation::PENDING)->with(['project.zumra', 'inviter'])->orderBy('created_at')->get(),
            'requests' => ParticipationRequest::query()->where('user_id', $user->getKey())->where('state', JoinRequestState::Pending->value)->with('project.zumra')->orderBy('created_at')->get(),
            'companyLinks' => ProjectCompanyLink::query()->where('state', ProjectCompanyLink::REQUESTED)->whereHas('company', fn (Builder $c) => $c->where('owner_id', $user->getKey()))->with(['project.zumra', 'company'])->orderBy('created_at')->get(),
        ]);
    }

    private function invitation(string $id): ProjectInvitation
    {
        abort_unless(Str::isUuid($id), 404);

        return ProjectInvitation::query()->where('user_id', Auth::id())->findOrFail($id);
    }

    private function link(string $id): ProjectCompanyLink
    {
        abort_unless(Str::isUuid($id), 404);

        return ProjectCompanyLink::query()->findOrFail($id);
    }
}
