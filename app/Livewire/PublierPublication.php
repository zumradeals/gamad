<?php

namespace App\Livewire;

use App\Enums\PublicationType;
use App\Livewire\Concerns\PicksSkills;
use App\Models\City;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Zumra;
use App\Services\Publications;
use App\Support\Experience;
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
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Publier')]
class PublierPublication extends Component
{
    use PicksSkills;

    #[Locked]
    public ?string $zumraId = null;

    /** Le projet au nom duquel on publie une avancée ou une réalisation (Lot 4b) ; sa ZUMRA est alors `zumraId`. */
    #[Locked]
    public ?string $projectId = null;

    public string $slug = '';

    public string $body = '';

    public string $needPurpose = '';

    public string $needTiming = '';

    /** @var array<int, string> */
    public array $skills = [];

    public string $skillsInput = '';

    public string $domain_id = '';

    public string $city_id = '';

    public string $visibility = Publication::PUBLIC;

    public function mount(string $slug, ?Zumra $zumra = null, ?Project $project = null): void
    {
        $type = PublicationType::fromSlug($slug);
        abort_unless($type, 404);

        Gate::authorize('create', Publication::class);

        $zumra = $zumra?->exists ? $zumra : null;
        abort_if(Experience::initial() && ($zumra || $project?->exists), 403, 'Les publications collectives sont en pause.');

        // Au nom d'un projet (D-127, D-134) : avancée ou réalisation, par le fondateur et les responsables de sa ZUMRA.
        if ($project?->exists) {
            Gate::authorize('update', $project);
            abort_unless(in_array($type, PublicationType::forProject(), true), 404);

            $zumra = $project->zumra;
            $this->projectId = $project->id;
            $this->zumraId = $zumra->id;
            $this->slug = $slug;
            $this->domain_id = $zumra->domain_id;
            $this->city_id = (string) $zumra->city_id;
            $this->visibility = Publication::PUBLIC;

            return;
        }

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
        // Les erreurs d'un envoi précédent ne survivent pas à un nouvel envoi.
        $this->resetErrorBag();

        $body = $this->body;
        if ($this->type()->isNeed()) {
            $this->validate(['needPurpose' => ['nullable', 'string', 'max:150'], 'needTiming' => ['nullable', 'string', 'max:100']], [], ['needPurpose' => 'but du besoin', 'needTiming' => 'disponibilités']);
            // Le besoin reste obligatoire : des précisions seules ne doivent pas fabriquer une demande vide.
            $this->validate(['body' => ['required', 'string']], [], ['body' => 'besoin']);
            $parts = [Normalizer::multiline($body)];
            if (filled($this->needPurpose)) {
                $parts[] = 'Pour : '.Normalizer::display($this->needPurpose);
            }
            if (filled($this->needTiming)) {
                $parts[] = 'Où et quand : '.Normalizer::display($this->needTiming);
            }
            $body = implode("\n\n", $parts);
        }

        $publication = $publications->publish(Auth::user(), $this->type(), [
            'body' => $body,
            'domain_id' => $this->domain_id,
            'city_id' => $this->city_id,
            'visibility' => $this->visibility,
            'skills' => $this->skills,
        ], $this->zumra(), $this->project());

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
            'project' => $this->project(),
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

    /** Le projet au nom duquel on publie, après avoir revérifié le droit de le gérer. */
    private function project(): ?Project
    {
        if ($this->projectId === null) {
            return null;
        }

        $project = Project::query()->with('zumra')->findOrFail($this->projectId);

        Gate::authorize('update', $project);

        return $project;
    }

    private function type(): PublicationType
    {
        return PublicationType::fromSlug($this->slug) ?? abort(404);
    }
}
