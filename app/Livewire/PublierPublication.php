<?php

namespace App\Livewire;

use App\Enums\PublicationType;
use App\Livewire\Concerns\PicksSkills;
use App\Models\City;
use App\Models\Domain;
use App\Models\Publication;
use App\Services\Publications;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
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

    public string $slug = '';

    public string $body = '';

    /** @var array<int, string> */
    public array $skills = [];

    public string $skillsInput = '';

    public string $domain_id = '';

    public string $city_id = '';

    public string $visibility = Publication::PUBLIC;

    public function mount(string $slug): void
    {
        $type = PublicationType::fromSlug($slug);
        abort_unless($type, 404);

        Gate::authorize('create', Publication::class);

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
        ]);

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
        ]);
    }

    private function type(): PublicationType
    {
        return PublicationType::fromSlug($this->slug) ?? abort(404);
    }
}
