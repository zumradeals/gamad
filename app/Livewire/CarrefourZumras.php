<?php

namespace App\Livewire;

use App\Models\City;
use App\Models\Domain;
use App\Models\Zumra;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Carrefour ZUMRA : trouver une communauté par son nom, sa mission, son domaine ou sa ville. Ouvert aux visiteurs sans compte
 * (D-112). L'ordre est alphabétique : aucun classement (D-114). Seuls des chiffres réels sont affichés : le nombre de membres.
 */
#[Layout('components.layouts.app')]
#[Title('ZUMRA')]
class CarrefourZumras extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(as: 'ville', except: '')]
    public string $city = '';

    #[Url(as: 'domaine', except: '')]
    public string $domain = '';

    public function updatingQuery(): void
    {
        $this->resetPage();
    }

    public function updatingCity(): void
    {
        $this->resetPage();
    }

    public function updatingDomain(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('query', 'city', 'domain');
        $this->resetPage();
    }

    public function render()
    {
        $key = Normalizer::key(Str::limit($this->query, 100, ''));
        $city = Str::isUuid($this->city) ? $this->city : '';
        $domain = Str::isUuid($this->domain) ? $this->domain : '';
        $viewer = Auth::user();

        $zumras = Zumra::query()
            ->when($key !== '', fn (Builder $query) => $query->where('search', 'like', Normalizer::likeContains($key)))
            ->when($city !== '', fn (Builder $query) => $query->where('city_id', $city))
            ->when($domain !== '', fn (Builder $query) => $query->where('domain_id', $domain))
            ->with(['domain', 'city'])
            ->withCount(['memberships as members_count' => fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->whereNotNull('email_verified_at'))])
            ->orderBy('name_key')->orderBy('id')
            ->paginate(self::PER_PAGE);

        $mine = $viewer
            ? Zumra::query()->whereHas('memberships', fn (Builder $query) => $query->where('user_id', $viewer->getKey()))
                ->with(['domain', 'city', 'memberships' => fn ($query) => $query->where('user_id', $viewer->getKey())])
                ->withCount(['memberships as members_count' => fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->whereNotNull('email_verified_at'))])
                ->orderBy('name_key')->get()
            : collect();

        // Les listes proposent les valeurs actives, plus celle déjà choisie dans l'adresse si elle a été désactivée depuis.
        $cities = City::query()
            ->where(fn (Builder $query) => $query->where('active', true)->when($city !== '', fn (Builder $q) => $q->orWhere('id', $city)))
            ->get()->sortBy(fn (City $c) => Normalizer::key($c->label()))->values();
        $domains = Domain::query()
            ->where(fn (Builder $query) => $query->where('active', true)->when($domain !== '', fn (Builder $q) => $q->orWhere('id', $domain)))
            ->ordered()->get();

        return view('livewire.carrefour-zumras', [
            'zumras' => $zumras,
            'mine' => $mine,
            'cities' => $cities,
            'domains' => $domains,
            'hasFilters' => $key !== '' || $city !== '' || $domain !== '',
            'viewer' => $viewer,
        ]);
    }
}
