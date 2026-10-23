<?php

namespace App\Livewire;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Models\Wallet;
use App\Models\Zumra;
use App\Services\GeniusPay;
use App\Services\Payments;
use App\Services\Wallets;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Portefeuille ZAHAB (Lot 6, D-164) : le solde et les mouvements de la personne, ou de sa ZUMRA pour ses responsables. Rien d'autre
 * à faire ici tant que les recharges et les contributions ne sont pas construites.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Portefeuille')]
class PagePortefeuille extends Component
{
    use WithPagination;

    public ?Zumra $zumra = null;

    public string $amount = '';

    /** Le paiement dont la personne revient : le prestataire la renvoie ici avec son identifiant. */
    #[Url(as: 'paiement')]
    public ?string $returned = null;

    public function mount(Wallets $wallets, Payments $payments, ?Zumra $zumra = null): void
    {
        $this->zumra = $zumra;
        $wallet = $this->wallet($wallets);
        Gate::authorize('view', $wallet);

        // Retour de la page de paiement : on demande tout de suite l'état au prestataire plutôt que d'attendre son rappel.
        if ($this->returned !== null) {
            $this->refresh($payments, $wallet);
        }
    }

    /** Lance la recharge et envoie la personne sur la page de paiement du prestataire. */
    public function recharge(Wallets $wallets, Payments $payments): void
    {
        $wallet = $this->wallet($wallets);
        Gate::authorize('view', $wallet);
        $this->resetErrorBag();

        $this->validate(['amount' => ['required', 'integer']], [], ['amount' => 'montant']);

        $back = $this->zumra ? route('zumras.portefeuille', $this->zumra) : route('portefeuille');

        try {
            $payment = $payments->start(Auth::user(), $wallet, (int) $this->amount, $back, $back);
        } catch (PaymentException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }

        $this->redirect($payment->checkout_url);
    }

    /** Rafraîchit les paiements en attente de ce portefeuille (sondage de la page). */
    public function refresh(Payments $payments, ?Wallet $wallet = null): void
    {
        $wallet ??= $this->wallet(app(Wallets::class));

        foreach (Payment::query()->where('wallet_id', $wallet->getKey())->where('status', PaymentStatus::Pending->value)->whereNotNull('provider_reference')->latest()->limit(5)->get() as $payment) {
            try {
                $payments->reconcile($payment);
            } catch (PaymentException) {
                // Le prestataire ne répond pas : le paiement reste en attente, la page réessaiera.
            }
        }
    }

    public function render(Wallets $wallets, Payments $payments, GeniusPay $provider)
    {
        $wallet = $this->wallet($wallets);
        Gate::authorize('view', $wallet);

        return view('livewire.page-portefeuille', [
            'wallet' => $wallet,
            'balance' => $wallets->balance($wallet),
            'entries' => $wallet->entries()->paginate(20),
            'pending' => Payment::query()->where('wallet_id', $wallet->getKey())->where('status', PaymentStatus::Pending->value)->where('created_at', '>', now()->subHours(Payment::LIFETIME_HOURS))->latest()->get(),
            'returnedPayment' => $this->returned ? Payment::query()->where('wallet_id', $wallet->getKey())->find($this->returned) : null,
            'canRecharge' => $payments->available() && ! $wallet->isClosed(),
            'sandbox' => $provider->mode() === 'sandbox',
            'limits' => $payments->limits(),
        ]);
    }

    /**
     * Le portefeuille existe dès qu'on l'ouvre : il se crée à la première visite d'un titulaire autorisé. Pour une ZUMRA, la
     * vérification passe avant la création, pour qu'une personne étrangère ne puisse pas en fabriquer.
     */
    private function wallet(Wallets $wallets): Wallet
    {
        if ($this->zumra) {
            abort_unless(in_array($this->zumra->getKey(), Auth::user()->managedZumraIds(), true), 404);

            return $wallets->forZumra($this->zumra);
        }

        return $wallets->forPerson(Auth::user());
    }
}
