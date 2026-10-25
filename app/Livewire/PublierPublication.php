<?php

namespace App\Livewire;

use App\Enums\PublicationType;
use App\Livewire\Concerns\PicksSkills;
use App\Models\City;
use App\Models\Domain;
use App\Models\Project;
use App\Models\Publication;
use App\Models\Zumra;
use App\Services\MatchingConditions;
use App\Services\Publications;
use App\Support\Experience;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Publier (écrans 8 et 9) : un écran pour les quatre types de publication du Lot 2 (D-081). Le texte est un texte brut de
 * 500 caractères au plus ; toutes les règles sont vérifiées par App\Services\Publications, côté serveur.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Publier')]
class PublierPublication extends Component
{
    use PicksSkills, WithFileUploads;

    public $media = null;

    /** Format ou taille refusés : un message simple, sans attendre l'envoi final. */
    public function updatedMedia(): void
    {
        $this->resetErrorBag('media');
        try {
            $this->validate(
                ['media' => ['file', 'max:10240', 'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/webm,application/pdf']],
                ['media.*' => 'Ce fichier ne peut pas être ajouté. Choisissez une image JPEG, PNG ou WebP, une vidéo MP4 ou WebM, ou un PDF (10 Mo au plus).'],
            );
        } catch (ValidationException $exception) {
            $this->media = null;

            throw $exception;
        }
    }

    public function removeMedia(): void
    {
        $this->media = null;
        $this->resetErrorBag('media');
    }

    #[Locked]
    public ?string $zumraId = null;

    /** Le projet au nom duquel on publie une avancée ou une réalisation (Lot 4b) ; sa ZUMRA est alors `zumraId`. */
    #[Locked]
    public ?string $projectId = null;

    public string $slug = '';

    public array $matching = [];

    public function updatedMatching($value, ?string $key = null): void
    {
        if ($key === 'terms' && $value !== 'paid') {
            $this->matching['budget'] = '';
        }
    }

    public bool $previewing = false;

    /** Étape du formulaire personnel : 1 Détails, 2 Compléments ; l'aperçu (étape 3) est `previewing`. */
    public int $step = 1;

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

        $this->matching = MatchingConditions::publicationDefaults($type);
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

        $body = $this->composedBody();

        $publication = $publications->publish(Auth::user(), $this->type(), [
            'body' => $body,
            'domain_id' => $this->domain_id,
            'city_id' => $this->city_id,
            'visibility' => $this->visibility,
            'skills' => $this->skills,
            'media' => $this->media,
            ...(! $this->zumraId && ! $this->projectId ? ['matching' => $this->matching] : []),
        ], $this->zumra(), $this->project());

        session()->flash('status', 'Publié. Elle apparaît dans les publications.');
        session()->flash('published_id', $publication->id);
        if (! $this->zumraId && ! $this->projectId) {
            session()->flash('clear_draft', 'gamad:draft:'.Auth::id().':'.$this->slug);
        }

        return $this->redirect(route('publications.show', $publication), navigate: false);
    }

    /** Étape 1 → 2 : la question principale est la seule obligatoire. */
    public function next(): void
    {
        $this->checkBody();
        $this->step = 2;
    }

    /** Revient à l'étape précédente sans rien perdre de ce qui est saisi. */
    public function back(): void
    {
        if ($this->previewing) {
            $this->previewing = false;
            $this->step = 2;
        } elseif ($this->step > 1) {
            $this->step = 1;
        }
    }

    public function preview(): void
    {
        $this->checkBody();
        $this->previewing = true;
        $this->step = 2;
    }

    public function edit(): void
    {
        $this->previewing = false;
        $this->step = 1;
    }

    private function checkBody(): void
    {
        Gate::authorize('create', Publication::class);
        $body = $this->composedBody();
        if ($body === '' || mb_strlen($body) > Publication::BODY_MAX) {
            throw ValidationException::withMessages(['body' => 'Écrivez votre publication dans la limite de '.Publication::BODY_MAX.' caractères, précisions comprises.']);
        }
    }

    private function composedBody(): string
    {
        $body = $this->body;
        if ($this->type()->isNeed()) {
            $this->validate(['needPurpose' => ['nullable', 'string', 'max:150'], 'needTiming' => ['nullable', 'string', 'max:100']], [], ['needPurpose' => 'but du besoin', 'needTiming' => 'disponibilités']);
            // Le besoin reste obligatoire : des précisions seules ne doivent pas fabriquer une demande vide.
            $this->validate(['body' => ['required', 'string']], [], ['body' => 'besoin']);
            $body = Normalizer::multiline($body);
            if ($body === '') {
                throw ValidationException::withMessages(['body' => 'Décrivez ce dont vous avez besoin.']);
            }
            $parts = [$body];
            if (filled($this->needPurpose)) {
                $parts[] = 'Pour : '.Normalizer::display($this->needPurpose);
            }
            if (filled($this->needTiming)) {
                $parts[] = 'Où et quand : '.Normalizer::display($this->needTiming);
            }
            $body = implode("\n\n", $parts);
        }

        return Normalizer::multiline($body);
    }

    public function render()
    {
        return view('livewire.publier-publication', [
            'previewBody' => $this->previewing ? $this->composedBody() : '',
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
