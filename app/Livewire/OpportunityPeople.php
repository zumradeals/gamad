<?php

namespace App\Livewire;

use App\Exceptions\DiscoveryLimitReached;
use App\Models\Opportunity;
use App\Services\CompanyDiscovery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Découvrir des personnes (D3, D-204) : depuis une opportunité publiée et ouverte, le propriétaire d'une entreprise vérifiée retrouve les
 * personnes qui ont choisi d'être découvertes et qui proposent une compétence recherchée. Une recherche, pas une liste de candidats :
 * jamais « recommandé », « intéressé » ni « meilleur profil », aucun score, aucun total, aucune notification.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Découvrir des personnes')]
class OpportunityPeople extends Component
{
    #[Locked]
    public string $opportunityId = '';

    /** Repères de début de chaque page déjà parcourue (le premier est vide) ; la page courante est `index`. */
    public array $cursors = [null];

    public int $index = 0;

    /** Repère de la page suivante, tel que calculé au dernier affichage. */
    public ?string $next = null;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('discover', $opportunity);
        $this->opportunityId = $opportunity->id;
    }

    public function nextPage(): void
    {
        if ($this->next === null) {
            return;
        }
        $this->cursors = array_slice($this->cursors, 0, $this->index + 1);
        $this->cursors[] = $this->next;
        $this->index++;
    }

    public function previousPage(): void
    {
        $this->index = max(0, $this->index - 1);
    }

    public function render(CompanyDiscovery $discovery)
    {
        $opportunity = Opportunity::query()->with(['company', 'requiredSkills'])->findOrFail($this->opportunityId);
        $people = collect();
        $state = 'ok';
        $this->next = null;
        $cursor = $this->cursors[$this->index] ?? null;

        try {
            $page = $discovery->page(Auth::user(), $opportunity, is_string($cursor) ? $cursor : null);
            $people = $page['people'];
            $this->next = $page['next'];
        } catch (DiscoveryLimitReached) {
            $state = 'limit';
        } catch (AuthorizationException) {
            $state = 'closed';
        }

        return view('livewire.opportunity-people', [
            'opportunity' => $opportunity,
            'people' => $people,
            'state' => $state,
            'requiredIds' => $opportunity->requiredSkills->modelKeys(),
            'pages' => CompanyDiscovery::DAILY_PAGES,
        ]);
    }
}
