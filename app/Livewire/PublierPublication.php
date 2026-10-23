<?php

namespace App\Livewire;

use App\Enums\PublicationType;
use App\Livewire\Concerns\PicksSkills;
use App\Models\City;
use App\Models\Domain;
use App\Models\Publication;
use App\Models\Zumra;
use App\Services\Publications;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Publier (écrans 8 et 9) : un écran pour les quatre types de publication du Lot 2 (D-081). Le texte est un texte brut de
 * 500 caractères au plus ; toutes les règles sont vérifiées par App\Services\Publications, côté serveur.
 */
#[Layout('components.layouts.app')]
#[Title('Publier')]
class PublierPublication extends Component
{
    use PicksSkills;

    #[Locked]
    public ?string $zumraId = null;

    public string $slug = '';

    public string $body = '';

    /** @var array<int, string> */
    public array $skills = [];

    public string $skillsInput = '';

    public string $domain_id = '';

    public string $city_id = '';

    public string $visibility = Publication::PUBLIC;

    public function mount(string $slug, ?Zumra $zumra = null): void
    {
        $type = PublicationType::fromSlug($slug);
        abort_unless($type, 404);

        Gate::authorize('create', Publication::class);

        $zumra = $zumra?->exists ? $zumra : null;

        // Au nom d'une ZUMRA (D-116) : son fondateur et ses responsables, avec les types qui existent pour une ZUMRA ; sinon les
        // types d'une personne (l'Appel à rejoindre n'existe que pour une ZUMRA).
        if ($zumra) {
            Gate::authorize('manage', $zumra);
            abort_unless(in_array($type, PublicationType::forZumra(), true), 404);

            $this->zumraId = $zumra->id;
            $this->slug = $slug;
            // Domaine, ville et visibilité sont repris de la ZUMRA : on peut les changer pour cette publication.
            $this->domain_id = $zumra->domain_id;
            $this->city_id = (string) $zumra->city_id;
            $this->visibility = Publication::PUBLIC;

            return;
        }

        abort_unless(in_array($type, PublicationType::forPerson(), true), 404);

        $profile = Auth::user()->ensureProfile();

        $this->slug = $slug;
        // Lieu et visibilité sont repris du profil : on peut les changer pour cette publication.
        $this->city_id = (string) ($profile->city?->active ? $profile->city_id : '');
        $this->visibility = $profile->visibility === Publication::MEMBERS ? Publication::MEMBERS : Publication::PUBLIC;
    }

    /** @return array<string, int> */
    protected function skillLists(): array
    {
        return ['skills' => Publication::MAX_SKILLS];
    }

    public function publish(Publications $publications)
    {
        $publication = $publications->publish(Auth::user(), $this->type(), [
            'body' => $this->body,
            'domain_id' => $this->domain_id,
            'city_id' => $this->city_id,
            'visibility' => $this->visibility,
            'skills' => $this->skills,
        ], $this->zumra());

        session()->flash('status', 'Publié. Elle apparaît dans le Fil.');

        return $this->redirect(route('publications.show', $publication), navigate: false);
    }

    public function render()
    {
        return view('livewire.publier-publication', [
            'type' => $this->type(),
            'domains' => Domain::query()->active()->ordered()->get(),
            'cities' => City::query()->active()->get()->sortBy(fn (City $city) => Normalizer::key($city->label()))->values(),
            'suggestions' => $this->skillSuggestions('skills'),
            'bodyMax' => Publication::BODY_MAX,
            'profileHasCity' => (bool) Auth::user()->ensureProfile()->city_id,
            'zumra' => $this->zumra(),
        ]);
    }

    /** La ZUMRA au nom de laquelle on publie, après avoir revérifié le droit de la gérer. */
    private function zumra(): ?Zumra
    {
        if ($this->zumraId === null) {
            return null;
        }

        $zumra = Zumra::query()->findOrFail($this->zumraId);

        Gate::authorize('manage', $zumra);

        return $zumra;
    }

    private function type(): PublicationType
    {
        return PublicationType::fromSlug($this->slug) ?? abort(404);
    }
}
