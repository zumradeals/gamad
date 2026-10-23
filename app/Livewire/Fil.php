<?php

namespace App\Livewire;

use App\Enums\GestureState;
use App\Enums\PublicationType;
use App\Models\Gesture;
use App\Models\Publication;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Fil global (écrans 1 et 4) : les publications visibles pour celui qui regarde, de la plus récente à la plus ancienne, sans
 * aucun algorithme de recommandation (D-087). Filtres : Tout, Besoins, Savoirs.
 */
#[Layout('components.layouts.app', ['wide' => true])]
#[Title('Fil')]
class Fil extends Component
{
    public const PAGE_SIZE = 10;

    public const MAX_SIZE = 200;

    #[Url(as: 'filtre', except: 'tout')]
    public string $filter = 'tout';

    public int $limit = self::PAGE_SIZE;

    public function setFilter(string $filter): void
    {
        $this->filter = in_array($filter, ['besoins', 'savoirs'], true) ? $filter : 'tout';
        $this->limit = self::PAGE_SIZE;
    }

    public function more(): void
    {
        $this->limit = min($this->limit + self::PAGE_SIZE, self::MAX_SIZE);
    }

    /** Un geste envoyé, retiré, accepté ou décliné : la page se redessine. */
    #[On('gesture-changed')]
    public function refresh(): void {}

    public function render()
    {
        $viewer = Auth::user();
        $filter = in_array($this->filter, ['besoins', 'savoirs'], true) ? $this->filter : 'tout';

        $posts = Publication::query()
            ->visibleTo($viewer)
            ->when($filter === 'besoins', fn ($query) => $query->where('type', PublicationType::Need->value))
            ->when($filter === 'savoirs', fn ($query) => $query->where('type', PublicationType::Knowledge->value))
            ->with(['author.profile', 'domain', 'city', 'skills'])
            ->withCount(['gestures as live_gestures_count' => fn ($query) => $query->whereIn('state', [GestureState::Sent->value, GestureState::Accepted->value])])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($this->limit + 1)
            ->get();

        $hasMore = $posts->count() > $this->limit;
        $posts = $posts->take($this->limit);

        return view('livewire.fil', [
            'posts' => $posts,
            'hasMore' => $hasMore && $this->limit < self::MAX_SIZE,
            'gestures' => Gesture::query()->where('user_id', $viewer->getKey())->whereIn('publication_id', $posts->pluck('id'))->get()->keyBy('publication_id'),
            'viewer' => $viewer,
            'profile' => $viewer->ensureProfile()->load(['city', 'offeredSkills', 'wantedSkills']),
            'filter' => $filter,
            'types' => PublicationType::cases(),
        ]);
    }
}
