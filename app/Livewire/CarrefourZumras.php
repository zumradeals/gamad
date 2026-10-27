<?php

namespace App\Livewire;

use App\Enums\ZumraFormat;
use App\Enums\ZumraSphere;
use App\Livewire\Concerns\GuardsZumraModule;
use App\Models\City;
use App\Models\Country;
use App\Models\Domain;
use App\Models\Zumra;
use App\Services\Contributions;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Carrefour ZUMRA (Z1, D-205) : trouver une communauté par son nom, son domaine ou sa localité, et la filtrer par nature (professionnelle,
 * sociale, religieuse) ou par fonctionnement (physique, numérique, hybride). Ouvert aux visiteurs sans compte quand le module est public
 * (D-112). Aucun classement de popularité (D-114) : tri par date de création ou par ordre alphabétique. « À la une » présente
 * seulement des ZUMRA reconnues par GAMAD, dans un ordre qui change chaque jour (règle neutre). Seuls des chiffres réels sont affichés.
 */
#[Layout('components.layouts.app')]
#[Title('ZUMRA')]
class CarrefourZumras extends Component
{
    use GuardsZumraModule, WithPagination;

    public const PER_PAGE = 12;

    public const FEATURED = 3;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(as: 'ville', except: '')]
    public string $city = '';

    #[Url(as: 'pays', except: '')]
    public string $country = '';

    #[Url(as: 'format', except: '')]
    public string $format = '';

    #[Url(as: 'domaine', except: '')]
    public string $domain = '';

    #[Url(as: 'nature', except: '')]
    public string $sphere = '';

    #[Url(as: 'tri', except: 'recentes')]
    public string $sort = 'recentes';

    #[Url(as: 'vue', except: 'grille')]
    public string $layout = 'grille';

    /** Seulement les ZUMRA reconnues officiellement par GAMAD (3c). */
    #[Url(as: 'reconnues', except: false)]
    public bool $recognized = false;

    public function updatingQuery(): void
    {
        $this->resetPage();
    }

    public function updatingCity(): void
    {
        $this->resetPage();
    }

    public function updatedCountry(): void
    {
        $this->city = '';
        $this->resetPage();
    }

    public function updatingFormat(): void
    {
        $this->resetPage();
    }

    /** Un filtre en un clic : la nature ou le fonctionnement choisi ; le même clic le retire. */
    public function pick(string $kind, string $value): void
    {
        if ($kind === 'sphere') {
            $this->sphere = $this->sphere === $value ? '' : $value;
        } elseif ($kind === 'format') {
            $this->format = $this->format === $value ? '' : $value;
        }

        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function updatingDomain(): void
    {
        $this->resetPage();
    }

    public function toggleRecognized(): void
    {
        $this->recognized = ! $this->recognized;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('query', 'city', 'country', 'format', 'domain', 'recognized', 'sphere');
        $this->resetPage();
    }

    public function render()
    {
        $key = Normalizer::key(Str::limit($this->query, 100, ''));
        $city = Str::isUuid($this->city) ? $this->city : '';
        $country = Str::isUuid($this->country) ? $this->country : '';
        $format = ZumraFormat::tryFrom($this->format);
        $domain = Str::isUuid($this->domain) ? $this->domain : '';
        $sphere = ZumraSphere::tryFrom($this->sphere);
        $viewer = Auth::user();
        $like = Normalizer::likeContains($key);

        $zumras = Zumra::query()->whereNull('suspended_at')->whereNull('closed_at')
            ->when($key !== '', fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('search', 'like', $like)
                ->orWhereHas('domain', fn (Builder $domains) => $domains->where('normalized', 'like', $like))
                ->orWhereHas('city', fn (Builder $cities) => $cities->where('normalized', 'like', $like))))
            ->when($sphere !== null, fn (Builder $query) => $query->where('sphere', $sphere->value))
            ->when($city !== '', fn (Builder $query) => $query->where('city_id', $city))
            ->when($country !== '', fn (Builder $query) => $query->whereHas('city', fn (Builder $q) => $q->where('country_id', $country)))
            ->when($format !== null, fn (Builder $query) => $query->where('format', $format->value))
            ->when($domain !== '', fn (Builder $query) => $query->where('domain_id', $domain))
            ->when($this->recognized, fn (Builder $query) => $query->whereNotNull('recognized_at'))
            ->with(['domain', 'city.country'])
            ->withCount(['memberships as members_count' => fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->active())])
            ->when($this->sort === 'alphabetique', fn (Builder $query) => $query->orderBy('name_key'), fn (Builder $query) => $query->orderByDesc('created_at'))
            ->orderBy('id')
            ->paginate(self::PER_PAGE);

        $hasFilters = $key !== '' || $city !== '' || $domain !== '' || $country !== '' || $format !== null || $sphere !== null || $this->recognized;

        // « À la une » : des ZUMRA reconnues par GAMAD, jamais un classement. L'ordre, neutre, change chaque jour (fuseau Africa/Abidjan).
        $seed = Carbon::today('Africa/Abidjan')->toDateString();
        $featured = $hasFilters || $zumras->currentPage() > 1
            ? collect()
            : Zumra::query()->whereNull('suspended_at')->whereNull('closed_at')->whereNotNull('recognized_at')->with(['domain', 'city.country'])
                ->withCount(['memberships as members_count' => fn (Builder $query) => $query->whereHas('user', fn (Builder $user) => $user->active())])
                ->orderByRaw('md5(zumras.id::text || ?)', [$seed])->limit(self::FEATURED)->get();

        // Les listes proposent les valeurs actives, plus celle déjà choisie dans l'adresse si elle a été désactivée depuis.
        $cities = City::query()
            ->where(fn (Builder $query) => $query->where(fn (Builder $q) => $q->where('active', true)
                ->when($country !== '', fn (Builder $q) => $q->where('country_id', $country)))
                ->when($city !== '', fn (Builder $q) => $q->orWhere('id', $city)))
            ->get()->sortBy(fn (City $c) => Normalizer::key($c->label()))->values();
        $countries = Country::query()
            ->where(fn (Builder $query) => $query->where('active', true)->when($country !== '', fn (Builder $q) => $q->orWhere('id', $country)))
            ->get()->sortBy(fn (Country $c) => Normalizer::key($c->name))->values();
        $domains = Domain::query()
            ->where(fn (Builder $query) => $query->where('active', true)->when($domain !== '', fn (Builder $q) => $q->orWhere('id', $domain)))
            ->ordered()->get();

        return view('livewire.carrefour-zumras', [
            'zumras' => $zumras,
            'engagedIds' => app(Contributions::class)->engagedZumraIds($zumras->pluck('id')->all()),
            'cities' => $cities,
            'domains' => $domains,
            'countries' => $countries,
            'formats' => ZumraFormat::cases(),
            'hasFilters' => $hasFilters,
            'featured' => $featured,
            'spheres' => ZumraSphere::cases(),
            'viewer' => $viewer,
            // Les ZUMRA de la page dans lesquelles la personne est déjà : pas de « Je veux rejoindre » pour elles.
            'memberOf' => $viewer ? $viewer->zumraMemberships()->whereIn('zumra_id', $zumras->pluck('id'))->pluck('zumra_id')->all() : [],
        ]);
    }
}
