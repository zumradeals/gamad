<?php

namespace App\Livewire;

use App\Models\City;
use App\Models\Domain;
use App\Models\Profile;
use App\Models\User;
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
 * Carrefour Personnes (écran 7) : trouver qui peut apporter une capacité, ou qui veut l'apprendre. Ouvert aux visiteurs
 * sans compte, qui ne voient que les profils publics (D-067).
 *
 * Une personne n'y figure que si elle a indiqué au moins une capacité dans le sens cherché. L'ordre est alphabétique :
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

    /** « offered » : je cherche quelqu'un qui peut l'apporter ; « wanted » : qui veut l'apprendre. */
    #[Url(as: 'sens', except: 'offered')]
    public string $mode = 'offered';

    #[Url(as: 'ville', except: '')]
    public string $city = '';

    #[Url(as: 'domaine', except: '')]
    public string $domain = '';

    public function setMode(string $mode): void
    {
        $this->mode = $mode === 'wanted' ? 'wanted' : 'offered';
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

    public function clearFilters(): void
    {
        $this->reset('query', 'city', 'domain');
        $this->resetPage();
    }

    public function render()
    {
        $mode = $this->mode === 'wanted' ? 'wanted' : 'offered';
        $relation = $mode === 'wanted' ? 'wantedSkills' : 'offeredSkills';
        $key = Normalizer::key(Str::limit($this->query, 100, ''));
        $city = Str::isUuid($this->city) ? $this->city : '';
        $domain = Str::isUuid($this->domain) ? $this->domain : '';
        $viewer = Auth::user();

        $people = Profile::query()
            ->visibleTo($viewer)
            ->whereHas($relation)
            ->when($key !== '', function (Builder $query) use ($key, $relation) {
                $like = Normalizer::likeContains($key);

                $query->where(function (Builder $query) use ($like, $relation) {
                    $query->whereHas($relation, fn (Builder $skills) => $skills->where('skills.normalized', 'like', $like))
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name_search', 'like', $like));
                });
            })
            ->when($city !== '', fn (Builder $query) => $query->where('city_id', $city))
            ->when($domain !== '', fn (Builder $query) => $query->whereHas('domains', fn (Builder $domains) => $domains->where('domains.id', $domain)))
            ->orderBy(User::query()->select('name_search')->whereColumn('users.id', 'profiles.user_id'))
            ->orderBy('profiles.id')
            ->with(['user', 'city', 'offeredSkills', 'wantedSkills'])
            ->paginate(self::PER_PAGE);

        // Les listes proposent les valeurs actives, plus celle déjà choisie dans l'adresse si elle a été désactivée depuis.
        $cities = City::query()
            ->where(fn (Builder $query) => $query->where('active', true)->when($city !== '', fn (Builder $q) => $q->orWhere('id', $city)))
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
            'hasFilters' => $key !== '' || $city !== '' || $domain !== '',
            'viewerProfile' => $viewer?->profile,
        ]);
    }
}
