<?php

namespace App\Livewire;

use App\Livewire\Concerns\GuardsContributionsModule;
use App\Models\ContributionProposal;
use App\Services\ContributionAccess;
use App\Services\ContributionProposals;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Détail d'une proposition de contribution (C1, D-207). Seuls la personne qui propose et les représentants du bénéficiaire voient le
 * message et la réponse ; une proposition publique se lit sans son message. Chaque action revérifie la politique côté serveur.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Contribution')]
class PageProposition extends Component
{
    use GuardsContributionsModule;

    #[Locked]
    public string $proposalId;

    /** Mot facultatif du bénéficiaire en réponse (280 caractères). */
    public string $note = '';

    public function mount(ContributionProposal $proposal): void
    {
        Gate::authorize('view', $proposal);

        $this->proposalId = $proposal->id;
    }

    public function accept(ContributionProposals $proposals): void
    {
        $proposals->accept(Auth::user(), $this->proposal(), $this->note);
        $this->reset('note');
        session()->flash('status', 'Proposition acceptée. Une conversation est ouverte avec la personne ; la contribution reste à réaliser.');
    }

    public function decline(ContributionProposals $proposals): void
    {
        $proposals->decline(Auth::user(), $this->proposal(), $this->note);
        $this->reset('note');
        session()->flash('status', 'Proposition refusée.');
    }

    public function withdraw(ContributionProposals $proposals): void
    {
        $proposals->withdraw(Auth::user(), $this->proposal());
        session()->flash('status', 'Proposition retirée.');
    }

    public function declareDone(ContributionProposals $proposals): void
    {
        $proposals->declareDone(Auth::user(), $this->proposal());
        session()->flash('status', 'Réalisation déclarée. Le bénéficiaire doit la confirmer.');
    }

    public function confirm(ContributionProposals $proposals): void
    {
        $proposals->confirm(Auth::user(), $this->proposal());
        session()->flash('status', 'Réalisation confirmée. Merci : cette contribution est comptée comme réalisée.');
    }

    public function render()
    {
        $viewer = Auth::user();
        $proposal = $this->proposal()->load(['author.profile', 'need', 'zumra', 'project', 'company', 'person', 'domain', 'city', 'conversation']);
        $access = app(ContributionAccess::class);
        $isAuthor = $proposal->user_id === $viewer->getKey();
        $handles = $access->handles($viewer, $proposal);

        return view('livewire.page-proposition', [
            'proposal' => $proposal,
            'viewer' => $viewer,
            'isAuthor' => $isAuthor,
            'handles' => $handles,
            'private' => $isAuthor || $handles,
            'can' => [
                'withdraw' => Gate::forUser($viewer)->allows('withdraw', $proposal),
                'decide' => Gate::forUser($viewer)->allows('decide', $proposal),
                'declare' => Gate::forUser($viewer)->allows('declare', $proposal),
                'confirm' => Gate::forUser($viewer)->allows('confirm', $proposal),
            ],
        ])->title($proposal->kind->label().' pour '.$proposal->beneficiaryName());
    }

    private function proposal(): ContributionProposal
    {
        $proposal = ContributionProposal::query()->findOrFail($this->proposalId);

        Gate::authorize('view', $proposal);

        return $proposal;
    }
}
