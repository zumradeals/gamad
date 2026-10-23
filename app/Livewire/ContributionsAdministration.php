<?php

namespace App\Livewire;

use App\Exceptions\ContributionException;
use App\Exceptions\WalletException;
use App\Models\Allocation;
use App\Models\Contribution;
use App\Models\Project;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Zumra;
use App\Services\Contributions;
use App\Services\Payments;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Tableau des contributions du Président (Lot 6c, D-168, D-170) : l'état du fonds, les dépenses de ZUMRA à valider, l'affectation
 * à des projets de ZUMRA engagées et reconnues, et le suivi des versements. Chaque validation et chaque affectation est inscrite
 * au journal d'administration. Seul le Président lit les noms des contributeurs ici.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Contributions')]
class ContributionsAdministration extends Component
{
    public string $projectId = '';

    public string $amount = '';

    public string $purpose = '';

    public ?string $message = null;

    public bool $messageIsError = false;

    public function mount(): void
    {
        Gate::authorize('super-admin');
    }

    public function validateContribution(Contributions $contributions, string $id): void
    {
        Gate::authorize('super-admin');

        $this->attempt(fn () => $contributions->validate(Auth::user(), Contribution::query()->findOrFail($id)), 'Dépense validée et débitée du portefeuille de la ZUMRA.');
    }

    public function refuseContribution(Contributions $contributions, string $id): void
    {
        Gate::authorize('super-admin');

        $this->attempt(fn () => $contributions->refuse(Auth::user(), Contribution::query()->findOrFail($id)), 'Demande refusée : rien n’a été débité.');
    }

    public function allocate(Contributions $contributions): void
    {
        Gate::authorize('super-admin');
        $this->resetErrorBag();

        $this->validate([
            'projectId' => ['required', 'uuid'],
            'amount' => ['required', 'integer', 'min:1'],
            'purpose' => ['required', 'string', 'max:255'],
        ], [], ['projectId' => 'projet', 'amount' => 'montant', 'purpose' => 'objet']);

        $this->attempt(function () use ($contributions) {
            $contributions->allocate(Auth::user(), Project::query()->findOrFail($this->projectId), (int) $this->amount, trim($this->purpose));
            $this->reset('projectId', 'amount', 'purpose');
        }, 'Affectation décidée : elle figure au rapport public, sans aucun nom de personne.');
    }

    public function markPaid(Contributions $contributions, string $id): void
    {
        Gate::authorize('super-admin');

        $this->attempt(fn () => $contributions->markPaid(Auth::user(), Allocation::query()->findOrFail($id)), 'Versement inscrit.');
    }

    public function render(Contributions $contributions, Payments $payments)
    {
        Gate::authorize('super-admin');

        $financeable = Zumra::query()->whereNotNull('recognized_at')->whereIn('id', $contributions->engagedZumraIds())->pluck('name', 'id');
        $awaiting = Contribution::query()->with('wallet')->where('status', Contribution::AWAITING)->oldest()->get();
        $recent = Contribution::query()->with('wallet')->latest()->limit(20)->get();
        $owners = $this->owners($recent->pluck('wallet'));

        return view('livewire.contributions-administration', [
            'fund' => $contributions->fund(),
            'monthly' => $contributions->monthly(),
            'orientations' => $contributions->orientations(),
            'awaiting' => $awaiting,
            'awaitingOwners' => $this->owners($awaiting->pluck('wallet')),
            'projects' => Project::query()->whereIn('zumra_id', $financeable->keys())->orderBy('name')->get(['id', 'name', 'zumra_id']),
            'zumraNames' => $financeable,
            'allocations' => Allocation::query()->where('environment', $contributions->environment())->latest()->limit(50)->get(),
            'recent' => $recent,
            'owners' => $owners,
            'reconciliation' => $payments->reconciliation($contributions->environment()),
            'mode' => $contributions->environment(),
            'threshold' => $contributions->threshold(),
        ]);
    }

    /**
     * Noms des titulaires de portefeuilles (le Président peut les lire, D-170) : une requête par type.
     *
     * @param  Collection<int, Wallet>  $wallets
     * @return array<string, string>
     */
    private function owners($wallets): array
    {
        $names = [];

        foreach (User::query()->whereIn('id', $wallets->where('owner_type', Wallet::PERSON)->pluck('owner_id'))->pluck('name', 'id') as $id => $name) {
            $names[$id] = $name;
        }

        foreach (Zumra::query()->whereIn('id', $wallets->where('owner_type', Wallet::ZUMRA)->pluck('owner_id'))->pluck('name', 'id') as $id => $name) {
            $names[$id] = $name;
        }

        return $names;
    }

    private function attempt(callable $action, string $success): void
    {
        try {
            $action();
            $this->message = $success;
            $this->messageIsError = false;
        } catch (ContributionException|WalletException $e) {
            $this->message = $e->getMessage();
            $this->messageIsError = true;
        }
    }
}
