<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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

    public function mount(Zumra $zumra): void
    {
        Gate::authorize('view', $zumra);

        $this->zumraId = $zumra->id;
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

        return view('livewire.monde-zumra', [
            'zumra' => $zumra,
            'viewer' => $viewer,
            'canSeeMembers' => $canSeeMembers,
            'members' => $members,
            'role' => $zumra->roleOf($viewer),
        ])->title($zumra->name);
    }
}
