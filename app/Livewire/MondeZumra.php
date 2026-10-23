<?php

namespace App\Livewire;

use App\Enums\JoinRequestState;
use App\Models\Invitation;
use App\Models\JoinRequest;
use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Services\Invitations;
use App\Services\JoinRequests;
use App\Services\Memberships;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Monde d'une ZUMRA : l'en-tête est public, la liste des membres est réservée aux personnes connectées et vérifiées, avec la règle
 * de visibilité de chaque profil (D-112). Les onglets Formation, Fil et Discussion viennent avec la PR 3c, Projets avec le Lot 4.
 */
#[Layout('components.layouts.app')]
class MondeZumra extends Component
{
    public const MEMBERS_SHOWN = 100;

    #[Locked]
    public string $zumraId;

    /** Le mot facultatif de « Je veux rejoindre » (D-110). */
    public string $joinWord = '';

    public function mount(Zumra $zumra): void
    {
        Gate::authorize('view', $zumra);

        $this->zumraId = $zumra->id;
    }

    public function requestJoin(JoinRequests $requests): void
    {
        $requests->propose(Auth::user(), $this->zumra(), $this->joinWord);

        $this->reset('joinWord');
        session()->flash('status', 'Votre demande est envoyée. Le fondateur ou un responsable y répondra.');
    }

    public function withdrawRequest(JoinRequests $requests): void
    {
        $request = JoinRequest::query()->where('zumra_id', $this->zumraId)->where('user_id', Auth::id())->firstOrFail();

        $requests->withdraw(Auth::user(), $request);
        session()->flash('status', 'Votre demande est retirée.');
    }

    public function acceptInvitation(string $id, Invitations $invitations): void
    {
        $invitations->accept(Auth::user(), $this->invitation($id));
        session()->flash('status', 'Bienvenue dans la ZUMRA.');
    }

    public function refuseInvitation(string $id, Invitations $invitations): void
    {
        $invitations->refuse(Auth::user(), $this->invitation($id));
        session()->flash('status', 'Invitation refusée.');
    }

    public function leave(Memberships $memberships): void
    {
        $memberships->leave(Auth::user(), $this->zumra());

        session()->flash('status', 'Vous avez quitté la ZUMRA.');
    }

    public function acceptRequest(string $id, JoinRequests $requests): void
    {
        $requests->accept(Auth::user(), $this->joinRequest($id));
    }

    public function declineRequest(string $id, JoinRequests $requests): void
    {
        $requests->decline(Auth::user(), $this->joinRequest($id));
    }

    public function cancelInvitation(string $id, Invitations $invitations): void
    {
        abort_unless(Str::isUuid($id), 404);

        $invitations->cancel(Auth::user(), Invitation::query()->where('zumra_id', $this->zumraId)->findOrFail($id));
    }

    public function removeMember(string $id, Memberships $memberships): void
    {
        $memberships->remove(Auth::user(), $this->zumra(), $this->membership($id));
    }

    public function promote(string $id, Memberships $memberships): void
    {
        $memberships->promote(Auth::user(), $this->zumra(), $this->membership($id));
    }

    public function demote(string $id, Memberships $memberships): void
    {
        $memberships->demote(Auth::user(), $this->zumra(), $this->membership($id));
    }

    public function render()
    {
        $viewer = Auth::user();
        $zumra = Zumra::query()->with(['domain', 'city'])
            ->withCount(['memberships as members_count' => fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->whereNotNull('email_verified_at'))])
            ->findOrFail($this->zumraId);

        Gate::authorize('view', $zumra);

        $canSeeMembers = Gate::forUser($viewer)->allows('viewMembers', $zumra);

        // Les personnes dont l'adresse n'est pas vérifiée n'existent pas pour les autres : elles ne figurent ni dans la liste ni dans le compte.
        $members = $canSeeMembers
            ? ZumraMembership::query()->where('zumra_id', $zumra->id)
                ->whereHas('user', fn (Builder $user) => $user->whereNotNull('email_verified_at'))
                ->with(['user.profile.city'])
                ->orderByRaw("case role when 'founder' then 0 when 'manager' then 1 else 2 end")
                ->orderBy(User::query()->select('name_search')->whereColumn('users.id', 'zumra_memberships.user_id'))
                ->orderBy('id')
                ->limit(self::MEMBERS_SHOWN)->get()
            : collect();

        $role = $zumra->roleOf($viewer);
        $manages = (bool) $role?->manages();

        return view('livewire.monde-zumra', [
            'zumra' => $zumra,
            'viewer' => $viewer,
            'canSeeMembers' => $canSeeMembers,
            'members' => $members,
            'role' => $role,
            'manages' => $manages,
            // Pour une personne qui n'est pas dans la ZUMRA : sa demande, et l'invitation ouverte qu'elle a reçue.
            'myRequest' => $viewer && ! $role ? JoinRequest::query()->where('zumra_id', $zumra->id)->where('user_id', $viewer->getKey())->first() : null,
            'myInvitation' => $viewer && ! $role ? Invitation::query()->open()->where('zumra_id', $zumra->id)->where('user_id', $viewer->getKey())->with('inviter')->first() : null,
            // Pour le fondateur et les responsables : les demandes à traiter et les invitations en attente.
            'requests' => $manages
                ? JoinRequest::query()->where('zumra_id', $zumra->id)->where('state', JoinRequestState::Pending->value)
                    ->whereHas('user', fn (Builder $user) => $user->whereNotNull('email_verified_at'))
                    ->with(['user.profile.city', 'user.profile.offeredSkills'])->orderBy('created_at')->get()
                : collect(),
            'invitations' => $manages
                ? Invitation::query()->open()->where('zumra_id', $zumra->id)->with('user')->orderBy('created_at')->get()
                : collect(),
        ])->title($zumra->name);
    }

    private function zumra(): Zumra
    {
        $zumra = Zumra::query()->findOrFail($this->zumraId);

        Gate::authorize('view', $zumra);

        return $zumra;
    }

    private function joinRequest(string $id): JoinRequest
    {
        abort_unless(Str::isUuid($id), 404);

        return JoinRequest::query()->where('zumra_id', $this->zumraId)->findOrFail($id);
    }

    private function invitation(string $id): Invitation
    {
        abort_unless(Str::isUuid($id), 404);

        return Invitation::query()->where('zumra_id', $this->zumraId)->findOrFail($id);
    }

    private function membership(string $id): ZumraMembership
    {
        abort_unless(Str::isUuid($id), 404);

        return ZumraMembership::query()->where('zumra_id', $this->zumraId)->findOrFail($id);
    }
}
