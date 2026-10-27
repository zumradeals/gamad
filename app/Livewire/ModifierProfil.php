<?php

namespace App\Livewire;

use App\Livewire\Concerns\PicksSkills;
use App\Models\City;
use App\Models\Country;
use App\Models\Domain;
use App\Models\Profile;
use App\Models\Publication;
use App\Models\User;
use App\Services\MatchingConditions;
use App\Services\ProfileEditor;
use App\Services\ProfilePhoto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Modifier mon profil (écran 6). Les modifications restent dans la page jusqu'à « Enregistrer » : c'est alors seulement que
 * les capacités nouvelles sont créées, et que toutes les règles sont vérifiées côté serveur (App\Services\ProfileEditor).
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Modifier mon profil')]
class ModifierProfil extends Component
{
    use PicksSkills, WithFileUploads;

    public string $name = '';

    public string $bio = '';

    public string $headline = '';

    public string $cover_publication_id = '';

    public array $matching_preferences = [];

    public string $introduction = '';

    public array $experiences = [];

    public array $skill_details = [];

    public array $featured_publications = [];

    public function addExperience(): void
    {
        if (count($this->experiences) < 10) {
            $this->experiences[] = ['title' => '', 'kind' => 'personal', 'period' => '', 'description' => ''];
        }
    }

    public function removeExperience(int $index): void
    {
        unset($this->experiences[$index]);
        $this->experiences = array_values($this->experiences);
    }

    public function addSkillDetail(): void
    {
        if (count($this->skill_details) < 10) {
            $this->skill_details[] = ['skill' => $this->offered[0] ?? '', 'description' => '', 'publication_id' => ''];
        }
    }

    public function removeSkillDetail(int $index): void
    {
        unset($this->skill_details[$index]);
        $this->skill_details = array_values($this->skill_details);
    }

    public string $country_id = '';

    public string $city_id = '';

    /** Localité écrite librement quand la sienne n'est pas dans la liste (D-217). */
    public string $locality = '';

    /** Lieu à ajouter aux localités du matching : le choix se range tout de suite dans la liste (cinq au plus). */
    public string $zoneToAdd = '';

    public string $visibility = Profile::PUBLIC;

    /** @var array<int, string> */
    public array $offered = [];

    /** @var array<int, string> */
    public array $wanted = [];

    public string $offeredInput = '';

    public string $wantedInput = '';

    /** @var array<int, string> */
    public array $domain_ids = [];

    public $photo = null;

    public bool $removePhoto = false;

    public ?string $saved = null;

    public function mount(): void
    {
        $profile = $this->profile();

        $this->matching_preferences = array_replace(MatchingConditions::defaults(), $profile->matching_preferences ?? []);
        if ($profile->matching_preferences === null && $profile->city_id) {
            $this->matching_preferences['zones'] = [$profile->city_id];
        }
        $this->name = Auth::user()->name;
        $this->bio = (string) $profile->bio;
        $this->headline = (string) $profile->headline;
        $this->cover_publication_id = (string) $profile->cover_publication_id;
        if ($this->cover_publication_id !== '' && ! Publication::where('author_id', Auth::id())
            ->whereNull('zumra_id')->whereNull('hidden_at')->where('media_kind', 'image')
            ->whereNotNull('media_path')->whereKey($this->cover_publication_id)->exists()) {
            $this->cover_publication_id = '';
        }
        $this->introduction = (string) $profile->introduction;
        $this->experiences = $profile->experiences ?? [];
        $this->skill_details = $profile->skill_details ?? [];
        $this->featured_publications = $profile->featured_publications ?? [];
        $references = collect($this->featured_publications)->merge(collect($this->skill_details)->pluck('publication_id'))->filter()->unique();
        $valid = Publication::where('author_id', Auth::id())->whereNull('zumra_id')->whereNull('hidden_at')->whereIn('id', $references)->pluck('id')->all();
        $this->featured_publications = array_values(array_intersect($this->featured_publications, $valid));
        $this->skill_details = array_map(function ($detail) use ($valid) {
            if (! in_array($detail['publication_id'] ?? null, $valid, true)) {
                $detail['publication_id'] = null;
            }

            return $detail;
        }, $this->skill_details);
        $this->country_id = (string) $profile->country_id;
        $this->city_id = (string) $profile->city_id;
        $this->locality = (string) $profile->locality;
        $this->visibility = $profile->visibility;
        $this->offered = $profile->offeredSkills()->pluck('skills.name')->all();
        $this->wanted = $profile->wantedSkills()->pluck('skills.name')->all();
        $this->domain_ids = $profile->domains()->pluck('domains.id')->all();
    }

    /** @return array<string, int> */
    protected function skillLists(): array
    {
        return ['offered' => Profile::MAX_SKILLS_PER_KIND, 'wanted' => Profile::MAX_SKILLS_PER_KIND];
    }

    public function updatedZoneToAdd(): void
    {
        $id = $this->zoneToAdd;
        $this->zoneToAdd = '';
        $zones = (array) ($this->matching_preferences['zones'] ?? []);

        if (Str::isUuid($id) && count($zones) < 5 && ! in_array($id, $zones, true) && City::query()->active()->whereKey($id)->exists()) {
            $this->matching_preferences['zones'] = [...$zones, $id];
        }
    }

    public function removeZone(string $id): void
    {
        $this->matching_preferences['zones'] = array_values(array_filter((array) ($this->matching_preferences['zones'] ?? []), fn ($zone) => $zone !== $id));
    }

    public function updatedCountryId(): void
    {
        // Une ville appartient à un pays : changer de pays efface la ville.
        $this->city_id = '';
    }

    public function updatedPhoto(): void
    {
        $this->resetErrorBag('photo');
        $this->removePhoto = false;

        try {
            $this->validateOnly('photo', ['photo' => ['image', 'mimes:jpeg,png,webp', 'max:5120']], [
                'photo.image' => 'Ce fichier n’est pas une photo.',
                'photo.mimes' => 'La photo doit être au format JPEG, PNG ou WebP.',
                'photo.max' => 'La photo ne doit pas dépasser 5 Mo.',
                'photo.uploaded' => 'La photo n’a pas pu être envoyée (5 Mo au plus).',
            ]);
        } catch (ValidationException $e) {
            $this->photo = null;

            throw $e;
        }
    }

    public function dropPhoto(): void
    {
        $this->photo = null;
        $this->removePhoto = $this->profile()->hasPhoto();
        $this->resetErrorBag('photo');
    }

    public function toggleDomain(string $id): void
    {
        $this->resetErrorBag('domain_ids');

        if (in_array($id, $this->domain_ids, true)) {
            $this->domain_ids = array_values(array_diff($this->domain_ids, [$id]));

            return;
        }

        if (count($this->domain_ids) >= Profile::MAX_DOMAINS) {
            $this->addError('domain_ids', 'Trois domaines au plus : retirez-en un pour en choisir un autre.');

            return;
        }

        $this->domain_ids[] = $id;
    }

    public function save(ProfileEditor $editor): void
    {
        $this->saved = null;
        $this->resetErrorBag();

        /** @var User $user */
        $user = Auth::user();

        $editor->save(
            $user,
            [
                'name' => $this->name,
                'bio' => $this->bio,
                'headline' => $this->headline,
                'cover_publication_id' => $this->cover_publication_id,
                'matching_preferences' => $this->matching_preferences,
                'introduction' => $this->introduction,
                'experiences' => $this->experiences,
                'skill_details' => $this->skill_details,
                'featured_publications' => $this->featured_publications,
                'country_id' => $this->country_id,
                'city_id' => $this->city_id,
                'locality' => $this->locality,
                'visibility' => $this->visibility,
                'offered' => $this->offered,
                'wanted' => $this->wanted,
                'domain_ids' => $this->domain_ids,
            ],
            $this->photo ? $this->photo->get() : null,
            $this->removePhoto,
        );

        $this->photo = null;
        $this->removePhoto = false;
        $this->mount();
        $this->saved = 'Profil enregistré.';
    }

    public function render()
    {
        $profile = $this->profile();
        // Ce que le navigateur renvoie n'est jamais cru : un identifiant qui n'en est pas un est ignoré à l'affichage
        // (et refusé à l'enregistrement).
        $countryId = Str::isUuid($this->country_id) ? $this->country_id : '';
        $domainIds = array_values(array_filter($this->domain_ids, fn ($id) => is_string($id) && Str::isUuid($id)));

        return view('livewire.modifier-profil', [
            'profile' => $profile->load('user'),
            'countries' => Country::query()
                ->where(fn (Builder $query) => $query->where('active', true)->when($profile->country_id, fn (Builder $q) => $q->orWhere('id', $profile->country_id)))
                ->orderBy('sort_order')->orderBy('name')->get(),
            'domains' => Domain::query()
                ->where(fn (Builder $query) => $query->where('active', true)->orWhereIn('id', $domainIds))
                ->ordered()->get(),
            'suggestions' => [
                'offered' => $this->skillSuggestions('offered'),
                'wanted' => $this->skillSuggestions('wanted'),
            ],
            'photoAvailable' => ProfilePhoto::available(),
            'pendingPhotoUrl' => $this->pendingPhotoUrl(),
            'showPhoto' => ! $this->removePhoto && ($this->photo !== null || $profile->hasPhoto()),
            'bioMax' => Profile::BIO_MAX,
            'zoneCities' => City::query()->with('country:id,name')->whereIn('id', array_filter((array) ($this->matching_preferences['zones'] ?? []), fn ($id) => is_string($id) && Str::isUuid($id)))->get(),
            'ownPosts' => Publication::where('author_id', Auth::id())->whereNull('zumra_id')->whereNull('hidden_at')->orderByDesc('created_at')->limit(100)->get(['id', 'body', 'visibility', 'media_kind', 'media_path'])
                ->merge(Publication::where('author_id', Auth::id())->whereNull('zumra_id')->whereNull('hidden_at')->whereIn('id', collect($profile->featured_publications ?? [])->merge(collect($profile->skill_details ?? [])->pluck('publication_id'))->push($profile->cover_publication_id)->filter())->get(['id', 'body', 'visibility', 'media_kind', 'media_path']))->unique('id')->values(),
        ]);
    }

    /** Toujours relu en base : l'état affiché ne dépend jamais d'une relation gardée en mémoire. */
    private function profile(): Profile
    {
        $user = Auth::user();

        return Profile::query()->where('user_id', $user->getKey())->first() ?? $user->ensureProfile();
    }

    private function pendingPhotoUrl(): ?string
    {
        if ($this->photo === null || ! method_exists($this->photo, 'isPreviewable') || ! $this->photo->isPreviewable()) {
            return null;
        }

        return $this->photo->temporaryUrl();
    }
}
