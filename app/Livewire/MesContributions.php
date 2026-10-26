<?php

namespace App\Livewire;

use App\Enums\ProposalState;
use App\Livewire\Concerns\GuardsContributionsModule;
use App\Models\ContributionProposal;
use App\Services\ContributionFeed;
use App\Services\ContributionStats;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Mes contributions » et « Mes besoins » (C1, D-207), depuis Mon espace : mes propositions et leur état, les propositions que je reçois
 * au nom de ce que je représente (avec celles qui attendent ma réponse), et les besoins que je gère. Rien n'y est public.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Mes contributions')]
class MesContributions extends Component
{
    use GuardsContributionsModule;

    #[Url(as: 'onglet', except: 'contributions')]
    public string $tab = 'contributions';

    public function showTab(string $tab): void
    {
        $this->tab = in_array($tab, ['contributions', 'besoins', 'recues'], true) ? $tab : 'contributions';
    }

    public function render(ContributionFeed $feed, ContributionStats $stats)
    {
        $user = Auth::user();

        return view('livewire.mes-contributions', [
            'tab' => in_array($this->tab, ['contributions', 'besoins', 'recues'], true) ? $this->tab : 'contributions',
            'mine' => ContributionProposal::query()->where('user_id', $user->getKey())->with(['zumra', 'project', 'company', 'person', 'need'])->orderByDesc('created_at')->limit(50)->get(),
            'received' => $feed->handledBy($user)->with(['author.profile', 'zumra', 'project', 'company', 'person', 'need'])
                ->orderByRaw("case state when 'proposed' then 0 when 'declared' then 1 when 'accepted' then 2 else 3 end")->orderByDesc('created_at')->limit(50)->get(),
            'needs' => $feed->managedNeeds($user)->with(['zumra', 'project', 'company'])->withCount(['proposals as proposals_count' => fn ($q) => $q->whereIn('state', [ProposalState::Proposed->value, ProposalState::Accepted->value, ProposalState::Declared->value, ProposalState::Realized->value])])->limit(50)->get(),
            'stats' => $stats->personal($user),
        ]);
    }
}
