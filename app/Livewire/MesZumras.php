<?php

namespace App\Livewire;

use App\Enums\JoinRequestState;
use App\Livewire\Concerns\GuardsZumraModule;
use App\Models\Invitation;
use App\Models\JoinRequest;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Services\Invitations;
use App\Services\Memberships;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * « Mes ZUMRA » (Z1, D-205), depuis Mon espace : mes ZUMRA et mon rôle, mes invitations à accepter ou refuser, mes demandes en attente.
 * Les invitations et les demandes passent par les services existants ; rien n'est élargi.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Mes ZUMRA')]
class MesZumras extends Component
{
    use GuardsZumraModule;

    public function accept(string $id, Invitations $invitations): void
    {
        $invitations->accept(Auth::user(), $this->invitation($id));
        session()->flash('status', 'Bienvenue dans la ZUMRA.');
    }

    public function refuse(string $id, Invitations $invitations): void
    {
        $invitations->refuse(Auth::user(), $this->invitation($id));
        session()->flash('status', 'Invitation refusée.');
    }

    public function acceptDuty(string $id, Memberships $memberships): void
    {
        $memberships->acceptDuty(Auth::user(), $this->dutyProposal($id));
        session()->flash('status', 'Responsabilité acceptée.');
    }

    public function declineDuty(string $id, Memberships $memberships): void
    {
        $memberships->declineDuty(Auth::user(), $this->dutyProposal($id));
        session()->flash('status', 'Responsabilité refusée.');
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.mes-zumras', [
            'memberships' => $user->zumraMemberships()->with(['zumra.domain', 'zumra.city'])
                ->whereHas('zumra')->get()->sortBy(fn ($membership) => $membership->zumra->name_key)->values(),
            'invitations' => Invitation::query()->open()->where('user_id', $user->getKey())->with(['zumra.domain', 'inviter'])->orderBy('created_at')->get(),
            'proposals' => $user->zumraMemberships()->whereNotNull('duty')->where('duty_state', ZumraMembership::DUTY_PENDING)->with('zumra')->whereHas('zumra')->get(),
            'requests' => JoinRequest::query()->where('user_id', $user->getKey())->where('state', JoinRequestState::Pending->value)
                ->whereHas('zumra', fn (Builder $zumra) => $zumra->whereNotNull('id'))->with('zumra')->orderBy('created_at')->get(),
            'counts' => Zumra::query()->whereIn('id', $user->zumraMemberships()->select('zumra_id'))
                ->withCount(['memberships as members_count' => fn (Builder $query) => $query->whereHas('user', fn (Builder $member) => $member->active())])
                ->pluck('members_count', 'id'),
        ]);
    }

    private function dutyProposal(string $id): ZumraMembership
    {
        abort_unless(Str::isUuid($id), 404);

        return ZumraMembership::query()->where('user_id', Auth::id())->findOrFail($id);
    }

    private function invitation(string $id): Invitation
    {
        abort_unless(Str::isUuid($id), 404);

        return Invitation::query()->where('user_id', Auth::id())->findOrFail($id);
    }
}
