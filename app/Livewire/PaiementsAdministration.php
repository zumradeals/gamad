<?php

namespace App\Livewire;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Services\GeniusPay;
use App\Services\Payments;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Paiements (Lot 6b, D-169) : le Président suit les recharges et leur rapprochement avec le registre, sans voir de nom (D-170 :
 * seul le type de portefeuille apparaît). Il peut demander le rapprochement tout de suite et terminer les essais avant le mode réel.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
class PaiementsAdministration extends Component
{
    use WithPagination;

    #[Url]
    public string $status = '';

    public ?string $message = null;

    public bool $messageIsError = false;

    public function mount(): void
    {
        Gate::authorize('super-admin');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function reconcile(Payments $payments): void
    {
        Gate::authorize('super-admin');

        $changed = $payments->reconcilePending();
        $this->message = $changed.' paiement(s) mis à jour.';
        $this->messageIsError = false;
    }

    public function endSandbox(Payments $payments): void
    {
        Gate::authorize('super-admin');

        try {
            [$wallets, $zahab] = $payments->endSandbox(Auth::user());
            $this->message = 'Essais terminés : '.$wallets.' portefeuille(s) remis à zéro, '.number_format($zahab, 0, ',', ' ').' ZAHAB d’essai retirés.';
            $this->messageIsError = false;
        } catch (PaymentException $e) {
            $this->message = 'Les essais ne peuvent se terminer qu’en mode d’essai, sans paiement réel.';
            $this->messageIsError = true;
        }
    }

    public function render(Payments $payments, GeniusPay $provider)
    {
        Gate::authorize('super-admin');

        $statuses = array_column(PaymentStatus::cases(), 'value');

        return view('livewire.paiements-administration', [
            'mode' => $provider->mode(),
            'report' => $payments->reconciliation($provider->mode()),
            'toReview' => Payment::query()->whereNotNull('note')->with('wallet')->latest()->limit(20)->get(),
            'payments' => Payment::query()->with('wallet')
                ->when(in_array($this->status, $statuses, true), fn ($q) => $q->where('status', $this->status))
                ->latest()->paginate(20),
            'statuses' => PaymentStatus::cases(),
            'residue' => $payments->sandboxResidue(),
            'canEndSandbox' => $provider->mode() === 'sandbox' && ! $payments->hasLivePayments(),
        ])->title('Paiements');
    }
}
