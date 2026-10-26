<?php

namespace App\Livewire;

use App\Enums\AdCta;
use App\Enums\AdObjective;
use App\Exceptions\AdException;
use App\Livewire\Concerns\GuardsPubliciteModule;
use App\Livewire\Concerns\PicksSkills;
use App\Models\AdCampaign;
use App\Models\City;
use App\Models\Domain;
use App\Models\Skill;
use App\Services\Publicite\AdImages;
use App\Services\Publicite\AudienceEstimator;
use App\Services\Publicite\Campaigns;
use App\Support\Normalizer;
use App\Support\PubliciteModule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * « Créer une campagne » et « Modifier » (D-209 à D-212), en six étapes de la maquette validée : Annonceur, Objectif, Contenu, Audience,
 * Budget et durée, Vérifier et soumettre. Sur ordinateur, une page à six sections ; sur téléphone, une étape par écran. Toutes les règles
 * vivent dans le service Campaigns : cet écran ne fait que les présenter. Aucun paiement : le budget est prévisionnel.
 */
#[Layout('components.layouts.app')]
#[Title('Créer une campagne')]
class CreerCampagne extends Component
{
    use GuardsPubliciteModule, PicksSkills, WithFileUploads;

    public ?string $campaignId = null;

    /** « person » (la personne elle-même) ou l'identifiant d'une entreprise vérifiée qu'elle possède. */
    public string $advertiser = 'person';

    public string $objective = '';

    public string $cta = '';

    public string $title = '';

    public string $body = '';

    public string $destination_url = '';

    public $image = null;

    public ?string $imagePath = null;

    public string $audience_mode = 'personalized';

    /** @var array<int, string> */
    public array $skills = [];

    public string $skillsInput = '';

    /** @var array<int, string> */
    public array $domainIds = [];

    /** @var array<int, string> */
    public array $cityIds = [];

    public string $cityInput = '';

    public bool $remote = false;

    public string $budget = '';

    public string $starts_on = '';

    public string $ends_on = '';

    public bool $rulesAccepted = false;

    public ?string $message = null;

    public function mount(?AdCampaign $campaign = null): void
    {
        $this->starts_on = now('Africa/Abidjan')->addDay()->toDateString();

        if ($campaign?->exists) {
            Gate::authorize('manage', $campaign);
            abort_unless(in_array($campaign->displayState()->value, ['draft', 'refused', 'ready', 'paused', 'active'], true), 404);
            $this->campaignId = $campaign->getKey();
            $this->advertiser = $campaign->company_id ?? 'person';
            $this->objective = $campaign->objective?->value ?? '';
            $this->cta = $campaign->cta?->value ?? '';
            $this->title = (string) $campaign->title;
            $this->body = (string) $campaign->body;
            $this->destination_url = (string) $campaign->destination_url;
            $this->imagePath = $campaign->image_path;
            $this->audience_mode = $campaign->audience_mode;
            $this->skills = $campaign->skills()->pluck('skills.name')->all();
            $this->domainIds = $campaign->domains()->pluck('domains.id')->all();
            $this->cityIds = $campaign->cities()->pluck('cities.id')->all();
            $this->remote = (bool) $campaign->remote;
            $this->budget = (string) ($campaign->budget_fcfa ?? '');
            $this->starts_on = $campaign->starts_on?->toDateString() ?? $this->starts_on;
            $this->ends_on = $campaign->ends_on?->toDateString() ?? '';
        }
    }

    protected function skillLists(): array
    {
        return ['skills' => 8];
    }

    public function updatedObjective(): void
    {
        $allowed = collect(AdObjective::tryFrom($this->objective)?->ctas() ?? [])->map->value->all();
        if (! in_array($this->cta, $allowed, true)) {
            $this->cta = $allowed[0] ?? '';
        }
    }

    public function setDuration(int $days): void
    {
        if (! in_array($days, [7, 14, 15, 30], true)) {
            return;
        }
        $start = $this->starts_on !== '' ? Carbon::parse($this->starts_on) : now('Africa/Abidjan')->addDay();
        $this->starts_on = $start->toDateString();
        $this->ends_on = $start->copy()->addDays($days - 1)->toDateString();
    }

    public function toggleDomain(string $id): void
    {
        $this->domainIds = in_array($id, $this->domainIds, true) ? array_values(array_diff($this->domainIds, [$id])) : (count($this->domainIds) < 8 ? [...$this->domainIds, $id] : $this->domainIds);
    }

    public function addCity(string $id): void
    {
        if (count($this->cityIds) < 5 && ! in_array($id, $this->cityIds, true) && City::query()->whereKey($id)->exists()) {
            $this->cityIds[] = $id;
        }
        $this->cityInput = '';
    }

    public function removeCity(string $id): void
    {
        $this->cityIds = array_values(array_diff($this->cityIds, [$id]));
    }

    public function removeImage(): void
    {
        $this->image = null;
        $this->imagePath = null;
    }

    public function saveDraft(Campaigns $campaigns, AdImages $images): void
    {
        $campaign = $this->persist($campaigns, $images);
        if ($campaign) {
            session()->flash('publicite_message', $campaign->state === 'review' ? 'Vos modifications sont envoyées à la modération.' : 'Brouillon enregistré.');
            $this->redirectRoute('publicite.campagnes');
        }
    }

    public function submit(Campaigns $campaigns, AdImages $images): void
    {
        $this->resetErrorBag();
        if (! $this->rulesAccepted) {
            $this->addError('rulesAccepted', 'Confirmez que votre annonce respecte les règles de publicité de GAMAD.');
            $this->dispatch('campaign-step', step: 6);

            return;
        }

        $campaign = $this->persist($campaigns, $images);
        if (! $campaign) {
            return;
        }
        try {
            if (in_array($campaign->state, ['draft', 'refused'], true)) {
                $campaigns->submit(Auth::user(), $campaign);
            }
        } catch (ValidationException $e) {
            $this->campaignId = $campaign->getKey();
            $this->failOn($e->errors());
            throw $e;
        } catch (AdException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        session()->flash('publicite_message', 'Votre campagne est envoyée à la modération. Vous la suivez ici ; rien n’est diffusé avant sa décision.');
        $this->redirectRoute('publicite.campagnes');
    }

    private function persist(Campaigns $campaigns, AdImages $images): ?AdCampaign
    {
        $this->resetErrorBag();
        $user = Auth::user();
        $existing = $this->campaignId ? AdCampaign::query()->findOrFail($this->campaignId) : null;
        if ($existing) {
            Gate::authorize('manage', $existing);
        } else {
            Gate::authorize('create', AdCampaign::class);
        }

        try {
            $skillIds = $this->resolveSkills();
            $newPath = $this->image ? $images->store($this->image) : null;
            $data = [
                'company_id' => $this->advertiser === 'person' ? null : $this->advertiser,
                'objective' => $this->objective ?: null, 'cta' => $this->cta ?: null,
                'title' => $this->title, 'body' => $this->body, 'destination_url' => $this->destination_url,
                'image_path' => $newPath ?? $this->imagePath,
                'audience_mode' => $this->audience_mode, 'remote' => $this->remote,
                'skills' => $skillIds, 'domains' => $this->domainIds, 'cities' => $this->cityIds,
                'budget_fcfa' => $this->budget === '' ? null : preg_replace('/\s+/', '', $this->budget),
                'starts_on' => $this->starts_on ?: null, 'ends_on' => $this->ends_on ?: null,
            ];
            $campaign = $campaigns->save($user, $existing, $data);
            if ($newPath && $existing?->image_path && $existing->image_path !== $newPath) {
                $images->delete($existing->image_path);
            }
            $this->campaignId = $campaign->getKey();
            $this->image = null;
            $this->imagePath = $campaign->image_path;

            return $campaign;
        } catch (ValidationException $e) {
            $this->failOn($e->errors());
            throw $e;
        } catch (AdException $e) {
            $this->addError('form', $e->getMessage());
            $this->dispatch('campaign-step', step: 6);

            return null;
        }
    }

    /** @return array<int, string> Les capacités choisies, résolues dans le référentiel commun : aucune capacité nouvelle n'est créée ici. */
    private function resolveSkills(): array
    {
        $ids = [];
        foreach ($this->skills as $name) {
            $id = Skill::query()->where('normalized', Normalizer::key($name))->value('id');
            if ($id === null) {
                throw ValidationException::withMessages(['skillsInput' => '« '.$name.' » n’est pas encore une capacité utilisée sur GAMAD : choisissez dans les suggestions.']);
            }
            $ids[] = $id;
        }

        return $ids;
    }

    /** Sur téléphone, ramène à l'étape de la première erreur. @param  array<string, array<int, string>>  $errors */
    private function failOn(array $errors): void
    {
        $step = ['company_id' => 1, 'objective' => 2, 'cta' => 2, 'title' => 3, 'body' => 3, 'destination_url' => 3, 'image' => 3, 'skillsInput' => 4, 'audience' => 4,
            'budget_fcfa' => 5, 'starts_on' => 5, 'ends_on' => 5];
        $first = array_key_first($errors);
        $this->dispatch('campaign-step', step: $step[$first] ?? 6);
    }

    public function render()
    {
        $user = Auth::user();
        $campaigns = app(Campaigns::class);
        $estimator = app(AudienceEstimator::class);
        $level = null;
        if ($this->audience_mode === 'personalized') {
            $ids = Skill::query()->whereIn('normalized', array_map(Normalizer::key(...), $this->skills))->pluck('id')->all();
            $level = $estimator->level($estimator->count($user, $ids, $this->domainIds, $this->cityIds, $this->remote));
        }
        $company = $this->advertiser !== 'person' ? collect($campaigns->advertisers($user)['companies'])->firstWhere('id', $this->advertiser) : null;
        $objective = AdObjective::tryFrom($this->objective);
        $citySuggestions = mb_strlen(trim($this->cityInput)) >= 2
            ? City::query()->where('name', 'ilike', '%'.addcslashes(trim($this->cityInput), '\\%_').'%')->whereNotIn('id', $this->cityIds)->orderBy('name')->limit(6)->get() : collect();

        return view('livewire.creer-campagne', [
            'advertisers' => $campaigns->advertisers($user),
            'objectives' => AdObjective::cases(),
            'ctas' => $objective?->ctas() ?? [],
            'domains' => Domain::query()->active()->ordered()->get(),
            'chosenCities' => City::query()->whereIn('id', $this->cityIds)->orderBy('name')->get(),
            'citySuggestions' => $citySuggestions,
            'skillSuggestions' => $this->skillSuggestions('skills'),
            'level' => $level,
            'levelLabel' => $level ? AudienceEstimator::label($level) : null,
            'gauge' => $level ? AudienceEstimator::gauge($level) : null,
            'advertiserName' => $company?->name ?? $user->name,
            'advertiserVerified' => $company !== null,
            'previewImage' => $this->image && $this->image->isPreviewable() ? $this->image->temporaryUrl() : ($this->imagePath && $this->campaignId ? route('publicite.image', $this->campaignId) : null),
            'ctaLabel' => AdCta::tryFrom($this->cta)?->label() ?? 'Échanger',
            'minAudienceKnown' => PubliciteModule::minimumAudience(),
            'editing' => $this->campaignId !== null,
        ]);
    }
}
