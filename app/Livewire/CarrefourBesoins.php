<?php

namespace App\Livewire;

use App\Enums\NeedState;
use App\Enums\PublicationType;
use App\Models\Domain;
use App\Models\Publication;
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
 * Carrefour Besoins (Lot 5.1, D-160, D-161) : trouver un besoin émis par une personne, une ZUMRA ou un projet. Ouvert aux visiteurs
 * sans compte, avec les mêmes règles de visibilité que le Fil ; une publication masquée n'y paraît jamais. Par défaut, seuls les
 * besoins ouverts s'affichent. Du plus récent au plus ancien, sans classement (PRODUIT §6, règle 4) ; le geste « Je peux aider »
 * reste sur la publication.
 */
#[Layout('components.layouts.app')]
#[Title('Besoins')]
class CarrefourBesoins extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    /** Filtre d'état : un état de besoin, ou « tous » ; « ouvert » est la valeur par défaut. */
    public const ALL = 'tous';

    public const EMITTERS = [
        'personne' => 'Personnes',
        'zumra' => 'ZUMRA',
        'projet' => 'Projets',
    ];

    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(as: 'etat', except: 'open')]
    public string $state = 'open';

    #[Url(as: 'ville', except: '')]
    public string $city = '';

    #[Url(as: 'domaine', except: '')]
    public string $domain = '';

    #[Url(as: 'emetteur', except: '')]
    public string $emitter = '';

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

    public function updatingEmitter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('query', 'state', 'city', 'domain', 'emitter');
        $this->resetPage();
    }

    public function render()
    {
        $viewer = Auth::user();
        $text = trim(Str::limit($this->query, 100, ''));
        $key = Normalizer::key($text);
        $state = $this->state === self::ALL ? null : (NeedState::tryFrom($this->state) ?? NeedState::Open);
        $showAll = $this->state === self::ALL;
        $city = Str::isUuid($this->city) ? $this->city : '';
        $domain = Str::isUuid($this->domain) ? $this->domain : '';
        $emitter = array_key_exists($this->emitter, self::EMITTERS) ? $this->emitter : '';

        $needs = Publication::query()
            ->visibleTo($viewer)
            ->whereNull('hidden_at')
            ->where('type', PublicationType::Need->value)
            ->when($state, fn (Builder $query) => $query->where('need_state', $state->value))
            ->when($city !== '', fn (Builder $query) => $query->where('city_id', $city))
            ->when($domain !== '', fn (Builder $query) => $query->where('domain_id', $domain))
            // L'émetteur : un projet s'il y en a un, sinon une ZUMRA, sinon la personne elle-même (un besoin de projet porte aussi la ZUMRA du projet).
            ->when($emitter === 'projet', fn (Builder $query) => $query->whereNotNull('project_id'))
            ->when($emitter === 'zumra', fn (Builder $query) => $query->whereNotNull('zumra_id')->whereNull('project_id'))
            ->when($emitter === 'personne', fn (Builder $query) => $query->whereNull('zumra_id'))
            // Recherche : le texte du besoin, ou la capacité demandée (sans tenir compte des accents ni de la casse).
            ->when($key !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($key) {
                $query->whereRaw(Normalizer::sqlKey('publications.body').' like ?', [Normalizer::likeContains($key)])
                    ->orWhereHas('skills', fn (Builder $skills) => $skills->where('skills.normalized', 'like', Normalizer::likeContains($key)));
            }))
            ->with(['author.profile', 'domain', 'city', 'skills', 'zumra', 'project'])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(self::PER_PAGE);

        $domains = Domain::query()
            ->where(fn (Builder $query) => $query->where('active', true)->when($domain !== '', fn (Builder $q) => $q->orWhere('id', $domain)))
            ->ordered()->get();

        // Un état par défaut (« ouverts ») n'est pas un filtre posé par la personne.
        $hasFilters = $key !== '' || $city !== '' || $domain !== '' || $emitter !== '' || $showAll || ($state !== null && $state !== NeedState::Open);

        return view('livewire.carrefour-besoins', [
            'needs' => $needs,
            'viewer' => $viewer,
            'managed' => $viewer?->managedZumraIds() ?? [],
            'states' => NeedState::cases(),
            'domains' => $domains,
            'emitters' => self::EMITTERS,
            'selectedState' => $showAll ? self::ALL : ($state?->value ?? 'open'),
            'hasFilters' => $hasFilters,
            'onlyOpen' => $state === NeedState::Open,
        ]);
    }
}
