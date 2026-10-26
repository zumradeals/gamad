<?php

namespace App\Livewire;

use App\Enums\AdState;
use App\Exceptions\AdException;
use App\Livewire\Concerns\GuardsPubliciteModule;
use App\Models\AdCampaign;
use App\Models\Company;
use App\Services\Publicite\Campaigns;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * « Mes campagnes » (D-209 à D-212) : les campagnes de la personne et de ses entreprises, leur état, et les gestes permis selon l'état
 * (modifier, retirer, mettre en pause, reprendre, arrêter, dupliquer, supprimer un brouillon). Les règles vivent dans le service ; les
 * droits dans la Policy. Rien n'est jamais payé : le budget affiché est prévisionnel.
 */
#[Layout('components.layouts.app')]
#[Title('Mes campagnes')]
class MesCampagnes extends Component
{
    use GuardsPubliciteModule;

    #[Url(as: 'etat', except: '')]
    public string $filter = '';

    public ?string $message = null;

    public bool $messageIsError = false;

    public ?string $confirmStop = null;

    public function mount(): void
    {
        $this->message = session('publicite_message');
    }

    public function setFilter(string $filter): void
    {
        $this->filter = AdState::tryFrom($filter) ? $filter : '';
    }

    public function withdraw(Campaigns $campaigns, string $id): void
    {
        $this->act(fn (AdCampaign $c) => $campaigns->withdraw($c), $id, 'La campagne est de retour dans vos brouillons.');
    }

    public function pause(Campaigns $campaigns, string $id): void
    {
        $this->act(fn (AdCampaign $c) => $campaigns->pause($c), $id, 'La campagne est en pause.');
    }

    public function resume(Campaigns $campaigns, string $id): void
    {
        $this->act(fn (AdCampaign $c) => $campaigns->resume($c), $id, 'La campagne reprend.');
    }

    public function askStop(string $id): void
    {
        Gate::authorize('manage', AdCampaign::query()->findOrFail($id));
        $this->confirmStop = $id;
    }

    public function cancelStop(): void
    {
        $this->confirmStop = null;
    }

    public function stop(Campaigns $campaigns, string $id): void
    {
        $this->confirmStop = null;
        $this->act(fn (AdCampaign $c) => $campaigns->stop($c), $id, 'La campagne est arrêtée. Vous pouvez la dupliquer pour en créer une nouvelle.');
    }

    public function delete(Campaigns $campaigns, string $id): void
    {
        $this->act(fn (AdCampaign $c) => $campaigns->delete($c), $id, 'Le brouillon est supprimé.');
    }

    public function duplicate(Campaigns $campaigns, string $id): void
    {
        $this->act(fn (AdCampaign $c) => $campaigns->duplicate(Auth::user(), $c), $id, 'Un nouveau brouillon a été créé à partir de cette campagne.');
    }

    private function act(\Closure $do, string $id, string $success): void
    {
        $campaign = AdCampaign::query()->findOrFail($id);
        Gate::authorize('manage', $campaign);
        $this->reset('message', 'messageIsError');

        try {
            $do($campaign);
            $this->message = $success;
        } catch (AdException $e) {
            $this->message = $e->getMessage();
            $this->messageIsError = true;
        }
    }

    public function render()
    {
        $user = Auth::user();
        $all = AdCampaign::query()->with(['company'])
            ->where(fn ($q) => $q->where('user_id', $user->getKey())->orWhereIn('company_id', Company::query()->where('owner_id', $user->getKey())->select('companies.id')))
            ->latest('updated_at')->get();

        $counts = $all->countBy(fn (AdCampaign $c) => $c->displayState()->value);
        $items = $this->filter === '' ? $all : $all->filter(fn (AdCampaign $c) => $c->displayState()->value === $this->filter)->values();

        return view('livewire.mes-campagnes', ['items' => $items, 'counts' => $counts, 'total' => $all->count(), 'states' => AdState::cases()]);
    }
}
