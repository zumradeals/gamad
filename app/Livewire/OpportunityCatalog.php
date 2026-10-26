<?php

namespace App\Livewire;

use App\Enums\OpportunityKind;
use App\Models\City;
use App\Models\Company;
use App\Models\Domain;
use App\Models\Opportunity;
use App\Services\Opportunities;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Catalogue des opportunités publiées par les entreprises vérifiées. Ouvert aux visiteurs ; les filtres sont tous réels et
 * combinables. Aucune note, aucun classement : tri par date de publication ou par date limite.
 */
#[Layout('components.layouts.app', ['extraWide' => true])]
#[Title('Opportunités')]
class OpportunityCatalog extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    public const DATES = ['' => 'Toutes les dates', '1' => 'Dernières 24 heures', '7' => '7 derniers jours', '30' => '30 derniers jours'];

    public const SORTS = ['recentes' => 'Plus récentes', 'echeance' => 'Date limite proche'];

    #[Url(as: 'q', except: '')]
    public string $query = '';

    #[Url(as: 'type', except: '')]
    public string $kind = '';

    #[Url(as: 'ville', except: '')]
    public string $city = '';

    #[Url(as: 'domaine', except: '')]
    public string $domain = '';

    #[Url(as: 'date', except: '')]
    public string $date = '';

    #[Url(as: 'tri', except: 'recentes')]
    public string $sort = 'recentes';

    public function setKind(string $kind): void
    {
        $this->kind = OpportunityKind::tryFrom($kind) ? $kind : '';
        $this->resetPage();
    }

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

    public function updatingDate(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    /** Sauvegarder ou retirer une opportunité de ma liste. */
    public function toggleSave(string $id, Opportunities $service): void
    {
        $user = Auth::user();
        abort_unless($user, 403);
        $opportunity = Opportunity::query()->open()->findOrFail($id);
        $saved = DB::table('opportunity_saves')->where('user_id', $user->id)->where('opportunity_id', $opportunity->id)->exists();
        $service->toggleSave($user, $opportunity, ! $saved);
    }

    public function clearFilters(): void
    {
        $this->reset('query', 'kind', 'city', 'domain', 'date');
        $this->resetPage();
    }

    /** Opportunités ouvertes correspondant aux filtres (hors recherche textuelle et tri) ; `$except` laisse de côté un filtre pour compter ses valeurs. */
    private function filtered(array $except = []): Builder
    {
        $kind = OpportunityKind::tryFrom($this->kind);
        $city = Str::isUuid($this->city) ? $this->city : '';
        $domain = Str::isUuid($this->domain) ? $this->domain : '';
        $days = array_key_exists($this->date, self::DATES) && $this->date !== '' ? (int) $this->date : 0;

        return Opportunity::query()->open()
            ->when($kind && ! in_array('kind', $except, true), fn (Builder $q) => $q->where('opportunities.kind', $kind->value))
            ->when($city !== '', fn (Builder $q) => $q->where('opportunities.city_id', $city))
            ->when($domain !== '', fn (Builder $q) => $q->where('opportunities.domain_id', $domain))
            ->when($days > 0, fn (Builder $q) => $q->where('opportunities.published_at', '>=', now()->subDays($days)));
    }

    public function render()
    {
        $viewer = Auth::user();
        $sort = array_key_exists($this->sort, self::SORTS) ? $this->sort : 'recentes';
        $key = Normalizer::key(Str::limit($this->query, 100, ''));

        $search = fn (Builder $q) => $q->when($key !== '', function (Builder $q) {
            $like = '%'.addcslashes(Str::limit($this->query, 100, ''), '%_\\').'%';
            $q->where(fn (Builder $w) => $w->where('opportunities.title', 'ilike', $like)->orWhere('opportunities.description', 'ilike', $like)
                ->orWhereRaw('opportunities.skills::text ilike ?', [$like])
                ->orWhereHas('company', fn (Builder $c) => $c->where('name', 'ilike', $like)));
        });

        $base = fn (array $except = []) => $this->filtered($except)->tap($search);

        $opportunities = $base()
            ->when($sort === 'echeance', fn (Builder $q) => $q->orderByRaw('opportunities.deadline asc nulls last')->orderByDesc('opportunities.published_at'))
            ->when($sort === 'recentes', fn (Builder $q) => $q->orderByDesc('opportunities.published_at'))
            ->orderBy('opportunities.id')
            ->with(['company', 'city', 'domain'])
            ->paginate(self::PER_PAGE);

        $kindCounts = $base(['kind'])->reorder()->select('opportunities.kind', DB::raw('count(*) as n'))->groupBy('opportunities.kind')->pluck('n', 'kind')->all();

        $savedIds = $viewer ? DB::table('opportunity_saves')->where('user_id', $viewer->id)->whereIn('opportunity_id', $opportunities->pluck('id'))->pluck('opportunity_id')->all() : [];

        $recent = Opportunity::query()->open()->with(['company', 'city'])->latest('published_at')->limit(3)->get();
        $companies = Company::query()->publiclyVisible()->whereHas('opportunities')->withCount('opportunities')->orderBy('name')->limit(4)->get();
        $forYou = $viewer?->profile ? $this->forYou($viewer->profile) : collect();

        return view('livewire.opportunity-catalog', [
            'opportunities' => $opportunities,
            'kinds' => OpportunityKind::cases(),
            'kindCounts' => $kindCounts,
            'totalCount' => array_sum($kindCounts),
            'cities' => City::query()->active()->get()->sortBy(fn (City $c) => Normalizer::key($c->label()))->values(),
            'domains' => Domain::query()->active()->ordered()->get(),
            'sort' => $sort,
            'savedIds' => $savedIds,
            'recent' => $recent,
            'companies' => $companies,
            'forYou' => $forYou,
            'activeFilters' => ($key !== '' ? 1 : 0) + ($this->kind !== '' ? 1 : 0) + ($this->city !== '' ? 1 : 0) + ($this->domain !== '' ? 1 : 0) + ($this->date !== '' ? 1 : 0),
            'publishCompany' => $viewer ? Company::query()->where('owner_id', $viewer->id)->get()->first(fn (Company $c) => $c->verified()) : null,
        ]);
    }

    /** Une sélection simple et explicable : une compétence que je propose figure dans l'intitulé, la description ou les compétences recherchées. */
    private function forYou($profile)
    {
        $names = $profile->offeredSkills()->pluck('name')->filter()->take(5)->all();
        if ($names === []) {
            return collect();
        }

        return Opportunity::query()->open()->with(['company', 'city'])
            ->where(function (Builder $q) use ($names) {
                foreach ($names as $name) {
                    $like = '%'.addcslashes($name, '%_\\').'%';
                    $q->orWhere('opportunities.title', 'ilike', $like)->orWhereRaw('opportunities.skills::text ilike ?', [$like]);
                }
            })
            ->latest('published_at')->limit(3)->get()
            ->map(function (Opportunity $o) use ($names) {
                $hit = collect($names)->first(fn ($n) => str_contains(Normalizer::key($o->title.' '.implode(' ', $o->skills ?? [])), Normalizer::key($n)));
                $o->setAttribute('reason', $hit ? 'Correspond à votre capacité « '.$hit.' »' : null);

                return $o;
            });
    }
}
