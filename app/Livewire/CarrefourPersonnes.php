<?php

namespace App\Livewire;

use App\Models\City;
use App\Models\Domain;
use App\Models\PersonConnection;
use App\Models\Profile;
use App\Models\User;
use App\Services\ComplementaryPeople;
use App\Services\People;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Personnes (écran 7) : trouver qui peut apporter une capacité, ou qui veut l'apprendre. Ouvert aux visiteurs
 * sans compte, qui ne voient que les profils publics (D-067).
 *
 * Une personne n'y figure que si elle a indiqué au moins une capacité (dans le sens cherché). Le tri est explicite :
 * alphabétique par défaut, jamais de note ni de classement de personnes (PRODUIT.md §6, règle 4). Les listes
 * « suivies », « contacts », « suggestions » et « autour de moi » ne reposent que sur des données déclarées par la
 * personne et ses relations ; aucune préférence privée de matching n'est lue ici.
 */
#[Layout('components.layouts.app', ['extraWide' => true])]
#[Title('Personnes')]
class CarrefourPersonnes extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    public const MODES = ['all', 'offered', 'wanted', 'domains', 'cities'];

    public const LISTS = ['toutes', 'suivies', 'contacts', 'suggestions', 'autour'];

    public const SORTS = ['nom', 'nom-desc', 'recents'];

    #[Url(as: 'q', except: '')]
    public string $query = '';

    /** « all » : toutes les capacités ; « offered » : qui peut l'apporter ; « wanted » : qui veut l'apprendre ; « domains » / « cities » : regroupements. */
    #[Url(as: 'sens', except: 'all')]
    public string $mode = 'all';

    #[Url(as: 'liste', except: 'toutes')]
    public string $list = 'toutes';

    #[Url(as: 'tri', except: 'nom')]
    public string $sort = 'nom';

    #[Url(as: 'ville', except: '')]
    public string $city = '';

    #[Url(as: 'domaine', except: '')]
    public string $domain = '';

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

    public function updatingSort(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('query', 'city', 'domain');
        $this->resetPage();
    }

    /** Suivre ou ne plus suivre : un suivi n'ouvre aucun contact (D-186). */
    public function follow(string $profileId, bool $follow, People $people): void
    {
        $viewer = Auth::user();
        abort_unless($viewer, 403);
        $profile = Profile::query()->findOrFail($profileId);
        Gate::authorize('view', $profile);
        $people->follow($viewer, $profile->user, $follow);
    }

    public function render()
    {
        $viewer = Auth::user();
        $mode = in_array($this->mode, self::MODES, true) ? $this->mode : 'all';
        $list = $viewer && in_array($this->list, self::LISTS, true) ? $this->list : 'toutes';
        $sort = in_array($this->sort, self::SORTS, true) ? $this->sort : 'nom';
        $key = Normalizer::key(Str::limit($this->query, 100, ''));
        $city = Str::isUuid($this->city) ? $this->city : '';
        $domain = Str::isUuid($this->domain) ? $this->domain : '';
        $viewerProfile = $viewer?->profile;

        $followedIds = $viewer ? DB::table('person_follows')->where('user_id', $viewer->id)->pluck('followed_id')->all() : [];
        $contacts = $viewer
            ? PersonConnection::query()->forPerson($viewer)->where('state', 'accepted')->get()
                ->mapWithKeys(fn (PersonConnection $c) => [$c->otherId($viewer) => $c->conversation_id])->all()
            : [];

        $base = fn (bool $withCity = true, bool $withDomain = true) => $this->candidates($viewer, $viewerProfile, $mode, $list, $key, $withCity ? $city : '', $withDomain ? $domain : '', $followedIds, array_keys($contacts));

        $people = $base()
            ->when($sort === 'recents', fn (Builder $q) => $q->orderByDesc('profiles.created_at'))
            ->when($sort !== 'recents', fn (Builder $q) => $q->orderBy(User::query()->select('name_search')->whereColumn('users.id', 'profiles.user_id'), $sort === 'nom-desc' ? 'desc' : 'asc'))
            ->orderBy('profiles.id')
            ->with(['user', 'city', 'domains', 'offeredSkills', 'wantedSkills'])
            ->paginate(self::PER_PAGE);

        // Compteurs réels : chaque filtre compte les personnes visibles qui répondraient, les autres filtres restant appliqués.
        $domainCounts = $base(true, false)->reorder()
            ->join('domain_profile', 'domain_profile.profile_id', '=', 'profiles.id')
            ->groupBy('domain_profile.domain_id')
            ->selectRaw('domain_profile.domain_id as id, count(distinct profiles.id) as n')
            ->pluck('n', 'id')->all();
        $cityCounts = $base(false, true)->reorder()
            ->whereNotNull('profiles.city_id')
            ->groupBy('profiles.city_id')
            ->selectRaw('profiles.city_id as id, count(*) as n')
            ->pluck('n', 'id')->all();

        // Les listes proposent les valeurs actives, plus celle déjà choisie dans l'adresse si elle a été désactivée depuis.
        // Seules les localités où vivent des personnes (et celle déjà choisie) : des milliers de lieux ne tiennent pas dans une liste (D-217).
        $cities = City::query()
            ->where(fn (Builder $query) => $query->where('active', true)->where(fn (Builder $q) => $q->whereIn('id', array_keys($cityCounts))->when($city !== '', fn (Builder $c) => $c->orWhere('id', $city))))
            ->get()
            ->sortBy(fn (City $c) => Normalizer::key($c->label()))
            ->values();
        $domains = Domain::query()
            ->where(fn (Builder $query) => $query->where('active', true)->when($domain !== '', fn (Builder $q) => $q->orWhere('id', $domain)))
            ->ordered()
            ->get();

        return view('livewire.carrefour-personnes', [
            'people' => $people,
            'mode' => $mode,
            'list' => $list,
            'sort' => $sort,
            'key' => $key,
            'cities' => $cities,
            'domains' => $domains,
            'domainCounts' => $domainCounts,
            'cityCounts' => $cityCounts,
            'cityId' => $city,
            'domainId' => $domain,
            'hasFilters' => $key !== '' || $city !== '' || $domain !== '',
            'viewerProfile' => $viewerProfile,
            'followedIds' => $followedIds,
            'contacts' => $contacts,
            'suggestions' => $viewer && $viewerProfile ? $this->suggestions($viewer, $viewerProfile, array_keys($contacts)) : collect(),
            'nearby' => $viewer && $viewerProfile?->city_id ? $this->nearby($viewer, $viewerProfile) : collect(),
        ]);
    }

    /** Personnes visibles correspondant aux critères, hors tri. */
    private function candidates(?User $viewer, ?Profile $mine, string $mode, string $list, string $key, string $city, string $domain, array $followedIds, array $contactIds): Builder
    {
        $query = Profile::query()->visibleTo($viewer);

        $skillScope = match ($mode) {
            'offered' => ['offeredSkills'],
            'wanted' => ['wantedSkills'],
            default => ['offeredSkills', 'wantedSkills'],
        };

        $query->where(function (Builder $query) use ($skillScope) {
            foreach ($skillScope as $relation) {
                $query->orWhereHas($relation);
            }
        });

        if ($key !== '') {
            $like = Normalizer::likeContains($key);
            $query->where(function (Builder $query) use ($like, $skillScope) {
                foreach ($skillScope as $relation) {
                    $query->orWhereHas($relation, fn (Builder $skills) => $skills->where('skills.normalized', 'like', $like));
                }
                $query->orWhereHas('user', fn (Builder $user) => $user->where('name_search', 'like', $like));
            });
        }

        $query->when($city !== '', fn (Builder $q) => $q->where('profiles.city_id', $city))
            ->when($domain !== '', fn (Builder $q) => $q->whereHas('domains', fn (Builder $domains) => $domains->where('domains.id', $domain)));

        if ($viewer && $list !== 'toutes') {
            $query->where('profiles.user_id', '<>', $viewer->id);
            match ($list) {
                'suivies' => $query->whereIn('profiles.user_id', $followedIds ?: [Str::uuid()->toString()]),
                'contacts' => $query->whereIn('profiles.user_id', $contactIds ?: [Str::uuid()->toString()]),
                'autour' => $mine?->city_id ? $query->where('profiles.city_id', $mine->city_id) : $query->whereRaw('1 = 0'),
                'suggestions' => app(ComplementaryPeople::class)->constrain($query, $mine),
                default => null,
            };
        }

        return $query;
    }

    /**
     * Quelques personnes dont les capacités sont complémentaires des miennes, avec la raison affichée. Ordre
     * alphabétique : une suggestion n'est pas un classement.
     */
    private function suggestions(User $viewer, Profile $mine, array $contactIds)
    {
        if (! $mine->hasSkills()) {
            return collect();
        }

        return app(ComplementaryPeople::class)->suggestions($viewer, $mine, $contactIds, 3, ['user', 'city', 'domains', 'offeredSkills', 'wantedSkills'], explainLearning: true);
    }

    /** Même localité déclarée que la mienne : aucune position, aucune distance. */
    private function nearby(User $viewer, Profile $mine)
    {
        return Profile::query()->visibleTo($viewer)->where('profiles.user_id', '<>', $viewer->id)->where('profiles.city_id', $mine->city_id)
            ->where(fn (Builder $q) => $q->whereHas('offeredSkills')->orWhereHas('wantedSkills'))
            ->orderBy(User::query()->select('name_search')->whereColumn('users.id', 'profiles.user_id'))
            ->with(['user', 'city', 'domains'])->limit(3)->get();
    }
}
