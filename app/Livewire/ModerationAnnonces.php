<?php

namespace App\Livewire;

use App\Enums\AdState;
use App\Exceptions\AdException;
use App\Models\AdCampaign;
use App\Services\Publicite\Campaigns;
use App\Support\PubliciteModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Modération préalable des annonces (D-210, D-212) : les campagnes En examen, de la plus ancienne à la plus récente. Accepter les rend
 * Prêtes ; Refuser exige un motif que l'annonceur lit. Réservé à la modération, jamais pour ses propres annonces ; chaque décision est
 * au journal d'administration et notifiée à l'annonceur. La modération voit l'annonce et son audience en critères, jamais de personne.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Modération des annonces')]
class ModerationAnnonces extends Component
{
    /** Motif saisi, par identifiant de campagne. @var array<string, string> */
    public array $reasons = [];

    public ?string $message = null;

    public bool $messageIsError = false;

    public function mount(): void
    {
        $this->authorizeAccess();
    }

    public function accept(Campaigns $campaigns, string $id): void
    {
        $this->decide($campaigns, $id, true, 'Annonce acceptée : elle est Prête, son annonceur en est informé.');
    }

    public function refuse(Campaigns $campaigns, string $id): void
    {
        $this->decide($campaigns, $id, false, 'Annonce refusée : son annonceur lit le motif.');
    }

    private function decide(Campaigns $campaigns, string $id, bool $accept, string $success): void
    {
        $this->authorizeAccess();
        $this->reset('message', 'messageIsError');
        $this->resetErrorBag();
        $campaign = AdCampaign::query()->findOrFail($id);
        Gate::authorize('moderate', $campaign);

        try {
            $campaigns->moderate(Auth::user(), $campaign, $accept, $this->reasons[$id] ?? null);
            unset($this->reasons[$id]);
            $this->message = $success;
        } catch (ValidationException $e) {
            $this->addError('reason-'.$id, $e->errors()['reason'][0] ?? 'Indiquez le motif.');
        } catch (AdException $e) {
            $this->message = $e->getMessage();
            $this->messageIsError = true;
        }
    }

    private function authorizeAccess(): void
    {
        Gate::authorize('moderate');
        abort_unless(PubliciteModule::accessibleTo(Auth::user()), 404);
    }

    public function render()
    {
        $this->authorizeAccess();

        $items = AdCampaign::query()->with(['company', 'owner', 'skills', 'domains', 'cities'])
            ->where('state', AdState::Review->value)->orderBy('submitted_at')->limit(50)->get();

        return view('livewire.moderation-annonces', ['items' => $items, 'me' => Auth::user()->getKey()]);
    }
}
