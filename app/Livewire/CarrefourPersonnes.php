<?php

namespace App\Livewire;

use App\Enums\PublicationType;
use App\Models\City;
use App\Models\Country;
use App\Models\Domain;
use App\Models\PersonConnection;
use App\Models\Profile;
use App\Models\User;
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
 * Carrefour Personnes (écran 7) : trouver qui peut apporter une capacité, ou qui veut l'apprendre. Ouvert aux visiteurs
 * sans compte, qui ne voient que les profils publics (D-067).
 *
 * Une personne n'y figure que si elle a indiqué au moins une capacité. L'ordre par défaut est alphabétique :
 * aucune note, aucun classement de personnes (PRODUIT.md §6, règle 4).
 */
#[Layout('components.layouts.app')]
#[Title('Personnes')]
class CarrefourPersonnes extends Component
{
    use WithPagination;

    public const PER_PAGE = 10;

    #[Url(as: 'q', except: '')]
    public string $query = '';

    /** Tous les profils, capacités proposées ou envies d’apprendre. */
    #[Url(as: 'sens', except: 'all')]
    public string $mode = 'all';

    #[Url(as: 'ville', except: '')]
    public string $city = '';

    #[Url(as: 'domaine', except: '')]
    public string $domain = '';

    #[Url(except: '')]
    public string $country = '';

    #[Url(except: 'all')]
    public string $section = 'all';

    #[Url(except: '')]
    public string $profileType = '';

    #[Url(except: false)]
    public bool $recentOffer = false;

    #[Url(except: 'name')]
    public string $sort = 'name';

    public function setMode(string $mode): void
    {
        $this->mode = in_array($mode, ['all', 'offered', 'wanted'], true) ? $mode : 'all';
        $this->resetPage();
    }

    public function setSection(string $section): void
    {
        $this->section = in_array($section, ['all', 'following', 'contacts', 'suggestions', 'nearby'], true) ? $section : 'all';
        $this->resetPage();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['query', 'city', 'domain', 'country', 'profileType', 'recentOffer', 'sort', 'mode', 'section'], true)) {
            if ($property === 'country') {
                $this->city = '';
            }
            $this->resetPage();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('query', 'city', 'domain', 'country', 'mode', 'section', 'profileType', 'recentOffer', 'sort');
        $this->resetPage();
    }

    public function follow(string $id, bool $following, People $people): void
    {
        abort_unless(Auth::check(), 403);
        $profile = Profile::visibleTo(Auth::user())->findOrFail($id);
        Gate::authorize('view', $profile);
        $people->follow(Auth::user(), $profile->user, $following);
    }

    public function render()
    {
        $mode = in_array($this->mode, ['offered', 'wanted'], true) ? $this->mode : 'all';
        $relations = $mode === 'all' ? ['offeredSkills', 'wantedSkills'] : [$mode === 'wanted' ? 'wantedSkills' : 'offeredSkills'];
        $key = Normalizer::key(Str::limit($this->query, 100, ''));
        $city = Str::isUuid($this->city) ? $this->city : '';
        $country = Str::isUuid($this->country) ? $this->country : '';
        $domain = Str::isUuid($this->domain) ? $this->domain : '';
        $viewer = Auth::user();
        $viewerProfile = $viewer?->profile;
        $following = $viewer ? DB::table('person_follows')->where('user_id', $viewer->id)->pluck('followed_id')->all() : [];
        $connections = $viewer ? PersonConnection::forPerson($viewer)->where('state', 'accepted')->get()->keyBy(fn ($pair) => $pair->otherId($viewer)) : collect();
        $base = Profile::visibleTo($viewer)
            ->where(fn (Builder $q) => $q->whereHas('offeredSkills')->orWhereHas('wantedSkills'));
        $suggested = (clone $base)->where('user_id', '!=', $viewer?->id)
            ->whereHas('offeredSkills', fn (Builder $q) => $q->whereIn('skills.id', $viewerProfile?->wantedSkills->modelKeys() ?? []));
        $nearby = (clone $base)->where('user_id', '!=', $viewer?->id)
            ->where('city_id', $viewerProfile?->city_id)->when(! $viewerProfile?->city_id, fn ($q) => $q->whereRaw('1 = 0'));
        $alphabetical = fn (Builder $q) => $q->orderBy(User::select('name_search')->whereColumn('users.id', 'profiles.user_id'))->orderBy('profiles.id');
        $relationsToLoad = ['user', 'city', 'country', 'offeredSkills', 'wantedSkills'];
        $people = (clone $base)
            ->where(function (Builder $q) use ($relations) {
                foreach ($relations as $relation) {
                    $q->orWhereHas($relation);
                }
            })
            ->when($key !== '', function (Builder $q) use ($key, $relations) {
                $like = Normalizer::likeContains($key);
                $q->where(function (Builder $q) use ($like, $relations) {
                    $q->whereHas('user', fn (Builder $u) => $u->where('name_search', 'like', $like));
                    foreach ($relations as $relation) {
                        $q->orWhereHas($relation, fn (Builder $skills) => $skills->where('skills.normalized', 'like', $like));
                    }
                });
            })
            ->when($city !== '', fn ($q) => $q->where('city_id', $city))
            ->when($country !== '', fn ($q) => $q->where('country_id', $country))
            ->when($domain !== '', fn ($q) => $q->whereHas('domains', fn ($d) => $d->where('domains.id', $domain)))
            ->when($this->section === 'following', fn ($q) => $q->whereIn('user_id', $following))
            ->when($this->section === 'contacts', fn ($q) => $q->whereIn('user_id', $connections->keys()))
            ->when($this->section === 'suggestions', fn ($q) => $q->whereIn('profiles.id', (clone $suggested)->select('profiles.id')))
            ->when($this->section === 'nearby', fn ($q) => $q->whereIn('profiles.id', (clone $nearby)->select('profiles.id')))
            ->when(in_array($this->profileType, ['professional', 'volunteer', 'learning', 'personal'], true), fn ($q) => $q->whereJsonContains('experiences', [['kind' => $this->profileType]]))
            ->when($this->recentOffer, fn ($q) => $q->whereHas('user.publications', fn ($p) => $p->visibleTo($viewer)
                ->where('type', PublicationType::Offer)->whereNull('hidden_at')->whereNull('company_id')->whereNull('zumra_id')->whereNull('project_id')
                ->where('created_at', '>=', now()->subDays(30))))
            ->when($this->sort === 'recent', fn ($q) => $q->orderByDesc('profiles.created_at'));
        $people = $alphabetical($people)->with($relationsToLoad)->paginate(self::PER_PAGE);
        $suggestions = $alphabetical($suggested)->with($relationsToLoad)->limit(3)->get();
        $nearbyPeople = $alphabetical($nearby)->with($relationsToLoad)->limit(3)->get();

        // Les listes proposent les valeurs actives, plus celle déjà choisie dans l'adresse si elle a été désactivée depuis.
        $cities = City::query()
            ->where(fn (Builder $query) => $query->where('active', true)->when($city !== '', fn (Builder $q) => $q->orWhere('id', $city)))
            ->when($country !== '', fn ($q) => $q->where('country_id', $country))
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
            'key' => $key,
            'cities' => $cities,
            'domains' => $domains,
            'cityId' => $city,
            'domainId' => $domain,
            'hasFilters' => $key !== '' || $city !== '' || $domain !== '' || $country !== '' || $this->section !== 'all' || $this->recentOffer || $this->profileType !== '',
            'countries' => Country::active()->orderBy('name')->get(),
            'suggestions' => $suggestions, 'nearbyPeople' => $nearbyPeople,
            'following' => $following, 'connections' => $connections,
            'viewerProfile' => $viewer?->profile,
        ]);
    }
}
