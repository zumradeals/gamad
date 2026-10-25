<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Profile;
use App\Models\User;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Annuaire des entreprises : uniquement les pages dont l'immatriculation a été vérifiée par GAMAD (le badge n'est donc pas
 * décoratif). Seules les données réellement enregistrées servent de filtre : secteur et localité saisis par l'entreprise,
 * opportunités publiées, suivis de la personne. Aucune taille, aucun type d'organisation, aucune distance n'est inventé.
 */
#[Layout('components.layouts.app', ['extraWide' => true])]
#[Title('Entreprises')]
class CompanyDirectory extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    public const MODES = ['all', 'opportunities', 'sectors', 'cities'];

    public const LISTS = ['toutes', 'suivies', 'suggestions', 'autour'];

    public const SORTS = ['nom', 'nom-desc', 'recents'];

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'onglet', except: 'all')]
    public string $mode = 'all';

    #[Url(as: 'liste', except: 'toutes')]
    public string $list = 'toutes';

    #[Url(as: 'tri', except: 'nom')]
    public string $sort = 'nom';

    #[Url(as: 'ville', except: '')]
    public string $city = '';

    #[Url(as: 'secteur', except: '')]
    public string $sector = '';

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, self::MODES, true) ? $mode : 'all';
        $this->resetPage();
    }

    public function setList(string $list): void
    {
        $this->list = in_array($list, self::LISTS, true) ? $list : 'toutes';
        $this->resetPage();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingCity(): void
    {
        $this->resetPage();
    }

    public function updatingSector(): void
    {
        $this->resetPage();
    }

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'city', 'sector');
        $this->resetPage();
    }

    /** Même règle que CompanyFollowController : personne active et vérifiée, entreprise vérifiée. */
    public function follow(string $companyId, bool $follow): void
    {
        $user = Auth::user();
        abort_unless($user?->fresh()->isActive() && $user->hasVerifiedEmail(), 403);
        $company = Company::query()->publiclyVisible()->find($companyId);
        abort_unless($company, 404);

        if ($follow) {
            DB::table('company_follows')->insertOrIgnore(['user_id' => $user->id, 'company_id' => $company->id, 'created_at' => now()]);
        } else {
            DB::table('company_follows')->where('user_id', $user->id)->where('company_id', $company->id)->delete();
        }
    }

    public function render()
    {
        $viewer = Auth::user();
        $mode = in_array($this->mode, self::MODES, true) ? $this->mode : 'all';
        $list = $viewer && in_array($this->list, self::LISTS, true) ? $this->list : 'toutes';
        $sort = in_array($this->sort, self::SORTS, true) ? $this->sort : 'nom';
        $search = trim(Str::limit(Normalizer::display($this->search), 100, ''));
        $city = $this->clean($this->city);
        $sector = $this->clean($this->sector);
        $mine = $viewer?->profile;
        $followedIds = $viewer ? DB::table('company_follows')->where('user_id', $viewer->id)->pluck('company_id')->all() : [];

        $base = fn (bool $withCity = true, bool $withSector = true) => $this->candidates($mode, $list, $search, $withCity ? $city : '', $withSector ? $sector : '', $mine, $followedIds);

        $companies = $base()
            ->when($sort === 'recents', fn (Builder $q) => $q->orderByDesc('verified_at'))
            ->when($sort !== 'recents', fn (Builder $q) => $q->orderBy('name', $sort === 'nom-desc' ? 'desc' : 'asc'))
            ->orderBy('id')
            ->with('opportunities')
            ->paginate(self::PER_PAGE);

        // Compteurs réels : chaque filtre compte les entreprises vérifiées qui répondraient, les autres filtres restant appliqués.
        $sectors = $this->facet($base(true, false), 'sector');
        $cities = $this->facet($base(false, true), 'city');

        return view('livewire.company-directory', [
            'companies' => $companies,
            'mode' => $mode,
            'list' => $list,
            'sort' => $sort,
            'search' => $search,
            'sectors' => $sectors,
            'cities' => $cities,
            'cityKey' => $city,
            'sectorKey' => $sector,
            'hasFilters' => $search !== '' || $city !== '' || $sector !== '',
            'followedIds' => $followedIds,
            'ownsCompany' => $viewer ? Company::query()->where('owner_id', $viewer->id)->exists() : false,
            'viewerCity' => $mine?->city,
            'suggestions' => $viewer && $mine ? $this->suggestions($viewer, $mine, $followedIds) : collect(),
            'nearby' => $viewer && $mine?->city ? $this->nearby($viewer, $mine) : collect(),
        ]);
    }

    private function clean(string $value): string
    {
        return mb_strtolower(trim(Str::limit(Normalizer::display($value), 120, '')));
    }

    private function candidates(string $mode, string $list, string $search, string $city, string $sector, ?Profile $mine, array $followedIds): Builder
    {
        $query = Company::query()->publiclyVisible();

        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'ilike', $like)->orWhere('sector', 'ilike', $like)
                ->orWhere('city', 'ilike', $like)->orWhere('summary', 'ilike', $like));
        }

        $query->when($mode === 'opportunities', fn (Builder $q) => $q->whereHas('opportunities'))
            ->when($city !== '', fn (Builder $q) => $q->whereRaw('lower(trim(city)) = ?', [$city]))
            ->when($sector !== '', fn (Builder $q) => $q->whereRaw('lower(trim(sector)) = ?', [$sector]));

        match (true) {
            $list === 'suivies' => $query->whereIn('companies.id', $followedIds ?: [Str::uuid()->toString()]),
            $list === 'autour' => $this->inCity($query, $mine),
            $list === 'suggestions' => $this->matchingSkills($query, $mine),
            default => null,
        };

        return $query;
    }

    /** Entreprises dont la localité saisie est celle déclarée par la personne : texte libre, donc aucune distance n'est calculée. */
    private function inCity(Builder $query, ?Profile $mine): void
    {
        $city = $mine?->city;
        if (! $city) {
            $query->whereRaw('1 = 0');

            return;
        }

        $names = array_values(array_unique(array_filter([mb_strtolower($city->name), mb_strtolower($city->label()), filled($city->area) ? mb_strtolower($city->area) : null])));
        $query->whereRaw('lower(trim(city)) in ('.implode(',', array_fill(0, count($names), '?')).')', $names);
    }

    /** Une capacité que je propose figure dans le secteur, la présentation ou une opportunité publiée de l'entreprise. */
    private function matchingSkills(Builder $query, ?Profile $mine): void
    {
        $skills = $this->skillNames($mine);
        if ($skills === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->where(function (Builder $q) use ($skills) {
            foreach ($skills as $name) {
                $like = '%'.addcslashes($name, '%_\\').'%';
                $q->orWhere('sector', 'ilike', $like)->orWhere('summary', 'ilike', $like)
                    ->orWhereHas('opportunities', fn (Builder $p) => $p->where('body', 'ilike', $like));
            }
        });
    }

    private function skillNames(?Profile $mine): array
    {
        return $mine ? $mine->offeredSkills->pluck('name')->filter()->take(Profile::MAX_SKILLS_PER_KIND)->all() : [];
    }

    private function facet(Builder $query, string $column): Collection
    {
        return $query->reorder()->whereNotNull($column)->whereRaw("trim($column) <> ''")
            ->groupByRaw("lower(trim($column))")
            ->selectRaw("lower(trim($column)) as facet_key, min(trim($column)) as label, count(*) as total")
            ->orderByRaw("lower(trim($column))")
            ->get();
    }

    /** Trois entreprises au plus, avec la raison explicable ; ordre alphabétique, jamais un classement. */
    private function suggestions(User $viewer, Profile $mine, array $followedIds): Collection
    {
        $skills = $this->skillNames($mine);
        if ($skills === []) {
            return collect();
        }

        $query = Company::query()->publiclyVisible()->where('owner_id', '<>', $viewer->id)
            ->whereNotIn('companies.id', $followedIds ?: [Str::uuid()->toString()]);
        $this->matchingSkills($query, $mine);

        return $query->orderBy('name')->with('opportunities')->limit(3)->get()->map(function (Company $company) use ($skills) {
            $reason = null;
            foreach ($skills as $name) {
                $needle = Normalizer::key($name);
                if (str_contains(Normalizer::key((string) $company->sector), $needle)) {
                    $reason = 'Votre capacité « '.$name.' » correspond à son secteur';
                } elseif ($company->opportunities->contains(fn ($p) => str_contains(Normalizer::key($p->body), $needle))) {
                    $reason = 'Une opportunité publiée mentionne « '.$name.' »';
                } elseif (str_contains(Normalizer::key((string) $company->summary), $needle)) {
                    $reason = 'Sa présentation mentionne « '.$name.' »';
                }
                if ($reason) {
                    break;
                }
            }
            $company->setAttribute('reason', $reason);

            return $company;
        });
    }

    private function nearby(User $viewer, Profile $mine): Collection
    {
        $query = Company::query()->publiclyVisible()->where('owner_id', '<>', $viewer->id);
        $this->inCity($query, $mine);

        return $query->orderBy('name')->limit(3)->get();
    }
}
