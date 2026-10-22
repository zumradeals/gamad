<?php

namespace App\Livewire;

use App\Models\City;
use App\Models\Country;
use App\Models\Domain;
use App\Models\Profile;
use App\Models\Skill;
use App\Models\User;
use App\Services\ProfileEditor;
use App\Services\ProfilePhoto;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Modifier mon profil (écran 6). Les modifications restent dans la page jusqu'à « Enregistrer » : c'est alors seulement que
 * les capacités nouvelles sont créées, et que toutes les règles sont vérifiées côté serveur (App\Services\ProfileEditor).
 */
#[Layout('components.layouts.app')]
#[Title('Modifier mon profil')]
class ModifierProfil extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $bio = '';

    public string $country_id = '';

    public string $city_id = '';

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

        $this->name = Auth::user()->name;
        $this->bio = (string) $profile->bio;
        $this->country_id = (string) $profile->country_id;
        $this->city_id = (string) $profile->city_id;
        $this->visibility = $profile->visibility;
        $this->offered = $profile->offeredSkills()->pluck('skills.name')->all();
        $this->wanted = $profile->wantedSkills()->pluck('skills.name')->all();
        $this->domain_ids = $profile->domains()->pluck('domains.id')->all();
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

    /** Ajoute une capacité à « offered » ou « wanted » : celle qu'on touche dans les suggestions, ou le texte saisi. */
    public function addSkill(string $list, ?string $name = null): void
    {
        abort_unless(in_array($list, ['offered', 'wanted'], true), 404);

        $input = $list.'Input';
        $this->resetErrorBag([$input, $list]);
        $display = Normalizer::display($name ?? $this->{$input});

        if ($display === '') {
            return;
        }

        $length = mb_strlen($display);

        if ($length < Skill::MIN_LENGTH || $length > Skill::MAX_LENGTH || ! preg_match('/[\p{L}\p{N}]/u', $display)) {
            $this->addError($input, 'Une capacité comporte de '.Skill::MIN_LENGTH.' à '.Skill::MAX_LENGTH.' caractères, dont au moins une lettre ou un chiffre.');

            return;
        }

        $key = Normalizer::key($display);

        if (in_array($key, array_map(Normalizer::key(...), $this->{$list}), true)) {
            $this->{$input} = '';

            return;
        }

        if (count($this->{$list}) >= Profile::MAX_SKILLS_PER_KIND) {
            $this->addError($input, 'Dix capacités au plus : retirez-en une pour en ajouter.');

            return;
        }

        // Si la capacité existe déjà, on garde son écriture d'origine (D-066) : « COMPTABILITE » devient « Comptabilité ».
        $existing = Skill::query()->where('normalized', $key)->value('name');

        $this->{$list}[] = $existing ?? $display;
        $this->{$input} = '';
    }

    public function removeSkill(string $list, int $index): void
    {
        abort_unless(in_array($list, ['offered', 'wanted'], true), 404);

        unset($this->{$list}[$index]);
        $this->{$list} = array_values($this->{$list});
        $this->resetErrorBag([$list.'Input', $list]);
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
                'country_id' => $this->country_id,
                'city_id' => $this->city_id,
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

        return view('livewire.modifier-profil', [
            'profile' => $profile->load('user'),
            'countries' => Country::query()
                ->where(fn (Builder $query) => $query->where('active', true)->when($profile->country_id, fn (Builder $q) => $q->orWhere('id', $profile->country_id)))
                ->orderBy('sort_order')->orderBy('name')->get(),
            'cities' => $this->country_id === '' ? collect() : City::query()
                ->where('country_id', $this->country_id)
                ->where(fn (Builder $query) => $query->where('active', true)->when($profile->city_id, fn (Builder $q) => $q->orWhere('id', $profile->city_id)))
                ->get()
                ->sortBy(fn (City $city) => Normalizer::key($city->label()))
                ->values(),
            'domains' => Domain::query()
                ->where(fn (Builder $query) => $query->where('active', true)->orWhereIn('id', $this->domain_ids))
                ->ordered()->get(),
            'suggestions' => [
                'offered' => $this->suggestions('offered'),
                'wanted' => $this->suggestions('wanted'),
            ],
            'photoAvailable' => ProfilePhoto::available(),
            'pendingPhotoUrl' => $this->pendingPhotoUrl(),
            'showPhoto' => ! $this->removePhoto && ($this->photo !== null || $profile->hasPhoto()),
            'bioMax' => Profile::BIO_MAX,
        ]);
    }

    private function profile(): Profile
    {
        return Auth::user()->ensureProfile();
    }

    private function pendingPhotoUrl(): ?string
    {
        if ($this->photo === null || ! method_exists($this->photo, 'isPreviewable') || ! $this->photo->isPreviewable()) {
            return null;
        }

        return $this->photo->temporaryUrl();
    }

    /**
     * Capacités déjà utilisées par d'autres personnes (ou par vous) qui contiennent la saisie, celles qui commencent par la
     * saisie d'abord. Une capacité que plus personne n'utilise n'est plus proposée.
     *
     * @return Collection<int, Skill>
     */
    private function suggestions(string $list)
    {
        $key = Normalizer::key($this->{$list.'Input'});

        if (mb_strlen($key) < 1) {
            return collect();
        }

        $selected = array_map(Normalizer::key(...), $this->{$list});

        return Skill::query()
            ->whereExists(fn ($query) => $query->select(DB::raw(1))->from('profile_skill')->whereColumn('profile_skill.skill_id', 'skills.id'))
            ->where('normalized', 'like', Normalizer::likeContains($key))
            ->whereNotIn('normalized', $selected)
            ->orderByRaw('case when normalized like ? then 0 else 1 end', [addcslashes($key, '\\%_').'%'])
            ->orderBy('normalized')
            ->limit(6)
            ->get();
    }
}
