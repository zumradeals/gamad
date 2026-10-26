<?php

namespace App\Livewire;

use App\Enums\OpportunityKind;
use App\Models\City;
use App\Models\Company;
use App\Models\Domain;
use App\Models\Opportunity;
use App\Services\Opportunities;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Publier une opportunité (entreprise) : une intention, les informations essentielles, des précisions facultatives, un aperçu.
 * Seule une entreprise vérifiée publie ; une entreprise non suspendue peut préparer un brouillon.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Publier une opportunité')]
class OpportunityForm extends Component
{
    #[Locked]
    public string $companyId = '';

    #[Locked]
    public ?string $opportunityId = null;

    public int $step = 1;

    /** Parcours téléphone : l'étape 1 ne choisit que le type, le poste et l'explication passent à l'étape 2. */
    public bool $compact = false;

    public string $kind = '';

    public string $title = '';

    public string $description = '';

    public string $missions = '';

    public string $profile = '';

    public string $skillsText = '';

    public string $experience = '';

    public string $qualifications = '';

    public string $conditions = '';

    public string $work_mode = '';

    public string $workload = '';

    public string $city_id = '';

    public string $domain_id = '';

    public string $deadline = '';

    public string $apply_mode = 'gamad';

    public string $external_url = '';

    public function mount(?Company $company = null, ?Opportunity $opportunity = null): void
    {
        if ($opportunity?->exists) {
            Gate::authorize('manage', $opportunity);
            $company = $opportunity->company;
            $this->opportunityId = $opportunity->id;
            $this->kind = $opportunity->kind->value;
            foreach (['title', 'description', 'missions', 'profile', 'experience', 'qualifications', 'conditions', 'work_mode', 'workload', 'city_id', 'domain_id', 'apply_mode', 'external_url'] as $field) {
                $this->{$field} = (string) $opportunity->{$field};
            }
            $this->skillsText = implode(', ', $opportunity->skills ?? []);
            $this->deadline = $opportunity->deadline?->format('Y-m-d') ?? '';
            $this->step = 2;
        } else {
            abort_unless($company?->exists, 404);
            Gate::authorize('manageFor', [Opportunity::class, $company]);
            $this->city_id = (string) City::query()->active()->where('name', $company->city)->value('id');
        }
        $this->companyId = $company->id;
    }

    private function company(): Company
    {
        $company = Company::query()->findOrFail($this->companyId);
        Gate::authorize('manageFor', [Opportunity::class, $company]);

        return $company;
    }

    private function opportunity(): ?Opportunity
    {
        if ($this->opportunityId === null) {
            return null;
        }
        $opportunity = Opportunity::query()->where('company_id', $this->companyId)->findOrFail($this->opportunityId);
        Gate::authorize('manage', $opportunity);

        return $opportunity;
    }

    public function chooseKind(string $kind): void
    {
        if (OpportunityKind::tryFrom($kind)) {
            $this->kind = $kind;
            $this->resetErrorBag('kind');
        }
    }

    private function payload(): array
    {
        return [
            'kind' => $this->kind, 'title' => $this->title, 'description' => $this->description, 'missions' => $this->missions,
            'profile' => $this->profile, 'skills' => preg_split('/[,;\n]+/', $this->skillsText) ?: [], 'experience' => $this->experience,
            'qualifications' => $this->qualifications, 'conditions' => $this->conditions, 'work_mode' => $this->work_mode,
            'workload' => $this->workload, 'city_id' => $this->city_id, 'domain_id' => $this->domain_id, 'deadline' => $this->deadline,
            'apply_mode' => $this->apply_mode, 'external_url' => $this->external_url,
        ];
    }

    /** Étape 1 → 2 : le type, l'intitulé et la description suffisent. */
    public function next(Opportunities $service): void
    {
        $this->resetErrorBag();
        $errors = [];
        if (! OpportunityKind::tryFrom($this->kind)) {
            $errors['kind'] = 'Choisissez ce que propose votre entreprise.';
        }
        if (Normalizer::display($this->title) === '') {
            $errors['title'] = 'Indiquez le poste ou le besoin.';
        } elseif (mb_strlen($this->title) > 160) {
            $errors['title'] = 'Le titre est trop long (160 caractères au plus).';
        }
        if (Normalizer::multiline($this->description) === '') {
            $errors['description'] = 'Expliquez ce que vous recherchez.';
        } elseif (mb_strlen($this->description) > 1000) {
            $errors['description'] = 'La description est trop longue (1 000 caractères au plus).';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $this->step = 2;
    }

    /** Téléphone : le type seul suffit pour passer aux détails (maquette mobile). */
    public function nextCompact(): void
    {
        $this->resetErrorBag();
        if (! OpportunityKind::tryFrom($this->kind)) {
            throw ValidationException::withMessages(['kind' => 'Choisissez ce que propose votre entreprise.']);
        }
        $this->compact = true;
        $this->step = 2;
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function edit(): void
    {
        $this->step = $this->compact ? 2 : 1;
    }

    public function preview(Opportunities $service): void
    {
        $this->resetErrorBag();
        $this->company();
        $service->clean($this->payload(), true);
        $this->step = 3;
    }

    public function saveDraft(Opportunities $service)
    {
        $this->resetErrorBag();
        $opportunity = $service->saveDraft(Auth::user(), $this->company(), $this->payload(), $this->opportunity());

        session()->flash('status', 'Brouillon enregistré. Il reste visible de votre entreprise seule.');

        return $this->redirect(route('entreprises.opportunities', $opportunity->company), navigate: false);
    }

    public function publish(Opportunities $service)
    {
        $this->resetErrorBag();
        $opportunity = $service->publish(Auth::user(), $this->company(), $this->payload(), $this->opportunity());

        session()->flash('status', 'Votre opportunité est publiée. Elle apparaît sur la page de votre entreprise et dans le catalogue.');

        return $this->redirect(route('opportunites.show', $opportunity), navigate: false);
    }

    public function render()
    {
        $company = $this->company();

        return view('livewire.opportunity-form', [
            'company' => $company,
            'verified' => $company->verified(),
            'kinds' => OpportunityKind::cases(),
            'kindEnum' => OpportunityKind::tryFrom($this->kind),
            'cities' => City::query()->active()->get()->sortBy(fn (City $c) => Normalizer::key($c->label()))->values(),
            'domains' => Domain::query()->active()->ordered()->get(),
            'editing' => $this->opportunityId !== null,
            'skillList' => collect(preg_split('/[,;\n]+/', $this->skillsText) ?: [])->map(fn ($s) => Normalizer::display($s))->filter()->unique()->take(Opportunity::MAX_SKILLS)->values()->all(),
        ]);
    }
}
