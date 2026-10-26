<?php

namespace App\Livewire;

use App\Models\Opportunity;
use App\Models\OpportunityResponse;
use App\Services\Opportunities;
use App\Services\People;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Les réponses reçues par une opportunité : privées, lisibles par l'entreprise émettrice seule. Aucune note, aucun classement. */
#[Layout('components.layouts.app', ['wide' => true])]
#[Title('Réponses reçues')]
class OpportunityResponses extends Component
{
    #[Locked]
    public string $opportunityId = '';

    public ?string $message = null;

    public function mount(Opportunity $opportunity): void
    {
        Gate::authorize('viewResponses', $opportunity);
        $this->opportunityId = $opportunity->id;
    }

    private function response(string $id): OpportunityResponse
    {
        return OpportunityResponse::query()->where('opportunity_id', $this->opportunityId)->with('opportunity.company')->findOrFail($id);
    }

    public function markSeen(string $id, Opportunities $service): void
    {
        $service->markSeen(Auth::user(), $this->response($id));
    }

    /** Propose un échange : une demande de relation, jamais une conversation imposée. */
    public function contact(string $id, Opportunities $service, People $people): void
    {
        $this->message = null;
        try {
            $service->contact(Auth::user(), $this->response($id), $people);
            $this->message = 'Votre proposition d’échange a été envoyée. La conversation s’ouvre si la personne accepte.';
        } catch (ValidationException $error) {
            $this->message = collect($error->errors())->flatten()->first();
        }
    }

    public function render()
    {
        $opportunity = Opportunity::query()->with('company', 'city')->findOrFail($this->opportunityId);
        Gate::authorize('viewResponses', $opportunity);

        $responses = $opportunity->responses()->where('state', '<>', OpportunityResponse::WITHDRAWN)
            ->whereHas('user', fn ($u) => $u->active())->with('user.profile.city')->latest()->get();

        return view('livewire.opportunity-responses', ['opportunity' => $opportunity, 'responses' => $responses, 'blockedIds' => People::excludedIds(Auth::user())]);
    }
}
