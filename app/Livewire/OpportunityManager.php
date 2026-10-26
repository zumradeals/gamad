<?php

namespace App\Livewire;

use App\Models\Company;
use App\Models\Opportunity;
use App\Services\Opportunities;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Les opportunités de mon entreprise : brouillons, publiées, clôturées, archivées. Réservé à son propriétaire. */
#[Layout('components.layouts.app', ['wide' => true])]
#[Title('Opportunités de mon entreprise')]
class OpportunityManager extends Component
{
    #[Locked]
    public string $companyId = '';

    #[Url(as: 'etat', except: 'toutes')]
    public string $tab = 'toutes';

    public ?string $message = null;

    public function mount(Company $company): void
    {
        Gate::authorize('manageFor', [Opportunity::class, $company]);
        $this->companyId = $company->id;
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['toutes', 'published', 'draft', 'closed', 'archived'], true) ? $tab : 'toutes';
    }

    private function mine(string $id): Opportunity
    {
        return Opportunity::query()->where('company_id', $this->companyId)->findOrFail($id);
    }

    public function close(string $id, Opportunities $service): void
    {
        $service->close(Auth::user(), $this->mine($id));
        $this->message = 'Opportunité clôturée : elle n’accepte plus de réponses.';
    }

    public function reopen(string $id, Opportunities $service): void
    {
        $service->reopen(Auth::user(), $this->mine($id));
        $this->message = 'Opportunité rouverte.';
    }

    public function archive(string $id, Opportunities $service): void
    {
        $service->archive(Auth::user(), $this->mine($id));
        $this->message = 'Opportunité archivée : elle n’est plus visible du public.';
    }

    public function deleteDraft(string $id, Opportunities $service): void
    {
        $service->deleteDraft(Auth::user(), $this->mine($id));
        $this->message = 'Brouillon supprimé.';
    }

    public function render()
    {
        $company = Company::query()->findOrFail($this->companyId);
        Gate::authorize('manageFor', [Opportunity::class, $company]);

        $all = $company->allOpportunities()->withCount(['responses as responses_count' => fn ($q) => $q->where('state', '<>', 'withdrawn')])->latest()->get();
        $counts = $all->groupBy('status')->map->count();

        return view('livewire.opportunity-manager', [
            'company' => $company,
            'verified' => $company->verified(),
            'items' => $this->tab === 'toutes' ? $all : $all->where('status', $this->tab)->values(),
            'counts' => $counts,
            'total' => $all->count(),
        ]);
    }
}
