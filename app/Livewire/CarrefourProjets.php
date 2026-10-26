<?php

namespace App\Livewire;

use App\Enums\ProjectState;
use App\Models\City;
use App\Models\Domain;
use App\Models\Project;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Carrefour Projets : trouver un projet par son nom, son but, son état, ou le domaine et la ville de sa ZUMRA. Ouvert aux
 * visiteurs sans compte (D-005). L'ordre est alphabétique : aucun classement (PRODUIT §6, règle 4).
 */
#[Layout('components.layouts.app')]
#[Title('Projets')]
class CarrefourProjets extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(as: 'etat', except: '')]
    public string $state = '';

    #[Url(as: 'ville', except: '')]
    public string $city = '';

    #[Url(as: 'domaine', except: '')]
    public string $domain = '';

    public function updatingQuery(): void
    {
        $this->resetPage();
    }

    public function updatingState(): void
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
        $this->reset('query', 'state', 'city', 'domain');
        $this->resetPage();
    }

    public function render()
    {
        $key = Normalizer::key(Str::limit($this->query, 100, ''));
        $state = ProjectState::tryFrom($this->state);
        $city = Str::isUuid($this->city) ? $this->city : '';
        $domain = Str::isUuid($this->domain) ? $this->domain : '';

        // Aucun projet privé n'y figure (P1) : seulement les présentations publiées par leur porteur.
        $projects = Project::query()->showcased()
            ->when($key !== '', fn (Builder $query) => $query->where('search', 'like', Normalizer::likeContains($key)))
            ->when($state, fn (Builder $query) => $query->where('state', $state->value))
            ->when($city !== '', fn (Builder $query) => $query->whereHas('zumra', fn (Builder $zumra) => $zumra->where('city_id', $city)))
            ->when($domain !== '', fn (Builder $query) => $query->whereHas('zumra', fn (Builder $zumra) => $zumra->where('domain_id', $domain)))
            ->with(['zumra.domain', 'zumra.city'])
            ->orderBy('name_key')->orderBy('id')
            ->paginate(self::PER_PAGE);

        $cities = City::query()
            ->where(fn (Builder $query) => $query->where('active', true)->when($city !== '', fn (Builder $q) => $q->orWhere('id', $city)))
            ->get()->sortBy(fn (City $c) => Normalizer::key($c->label()))->values();
        $domains = Domain::query()
            ->where(fn (Builder $query) => $query->where('active', true)->when($domain !== '', fn (Builder $q) => $q->orWhere('id', $domain)))
            ->ordered()->get();

        return view('livewire.carrefour-projets', [
            'projects' => $projects,
            'states' => ProjectState::cases(),
            'cities' => $cities,
            'domains' => $domains,
            'hasFilters' => $key !== '' || $state !== null || $city !== '' || $domain !== '',
        ]);
    }
}
