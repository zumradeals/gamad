<?php

namespace App\Livewire;

use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Exceptions\WalletException;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\Wallet;
use App\Models\Zumra;
use App\Services\GeniusPay;
use App\Services\Payments;
use App\Services\Payouts;
use App\Services\Wallets;
use App\Support\Experience;
use App\Support\WalletModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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

    /** Le reversement demandé (D-218) : montant, opérateur et numéro du titulaire indiqué. */
    public string $payoutAmount = '';

    public string $payoutOperator = '';

    public string $payoutPhone = '';

    public string $payoutHolder = '';

    public ?string $notice = null;

    public function mount(Wallets $wallets, Payments $payments, ?Zumra $zumra = null): void
    {
        $this->zumra = $zumra;
        $wallet = $this->wallet($wallets);

        if ($wallet === null) {
            return;
        }

        Gate::authorize('view', $wallet);

        // Retour de la page de paiement : on demande tout de suite l'état au prestataire plutôt que d'attendre son rappel.
        if ($this->returned !== null) {
            $this->refresh($payments, $wallet);
        }
    }

    /** Activation volontaire (D-218) : rien ne se crée tant que la personne, ou le responsable de la ZUMRA, ne le demande pas. */
    public function activate(Wallets $wallets): void
    {
        $wallets->activate(Auth::user(), $this->zumra);

        $this->notice = $this->zumra ? 'Le Zahab Wallet de la ZUMRA est activé.' : 'Votre Zahab Wallet est activé.';
    }

    /** Demande de reversement : GAMAD reverse la somme au titulaire du numéro indiqué (le Président exécute le versement). */
    public function requestPayout(Wallets $wallets, Payouts $payouts): void
    {
        $wallet = $this->wallet($wallets) ?? abort(404);
        Gate::authorize('view', $wallet);
        $this->resetErrorBag();
        $this->reset('notice');

        $this->validate(['payoutAmount' => ['required', 'integer']], [], ['payoutAmount' => 'montant']);

        try {
            $payouts->request(Auth::user(), $wallet, (int) $this->payoutAmount, $this->payoutOperator, $this->payoutPhone, $this->payoutHolder);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError('payout'.ucfirst($field), $messages[0]);
            }

            return;
        } catch (WalletException $e) {
            $this->addError('payoutAmount', $e->getMessage());

            return;
        }

        $this->reset('payoutAmount', 'payoutPhone', 'payoutHolder');
        $this->notice = 'Votre demande de reversement est enregistrée : la somme est mise de côté, et vous serez prévenu dès qu’elle est versée.';
    }

    public function cancelPayout(string $id, Wallets $wallets, Payouts $payouts): void
    {
        abort_unless(Str::isUuid($id), 404);
        $wallet = $this->wallet($wallets) ?? abort(404);
        Gate::authorize('view', $wallet);

        $payouts->cancel(Auth::user(), Payout::query()->where('wallet_id', $wallet->getKey())->findOrFail($id));
        $this->notice = 'Demande annulée : la somme est revenue dans votre portefeuille.';
    }

    /** Lance la recharge et envoie la personne sur la page de paiement du prestataire. */
    public function recharge(Wallets $wallets, Payments $payments): void
    {
        $wallet = $this->wallet($wallets) ?? abort(404);
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

        if ($wallet === null) {
            return;
        }

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

        if ($wallet === null) {
            return view('livewire.page-portefeuille', ['wallet' => null, 'canActivate' => WalletModule::accessibleTo(Auth::user()) && Auth::user()->hasVerifiedEmail(), 'sandbox' => $provider->mode() === 'sandbox']);
        }

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
            'canActivate' => false,
            'payoutMin' => app(Payouts::class)->minimum(),
            'operators' => Payout::OPERATORS,
            'payouts' => $wallet->payouts()->limit(10)->get(),
        ]);
    }

    /**
     * Le portefeuille existe dès qu'on l'ouvre : il se crée à la première visite d'un titulaire autorisé. Pour une ZUMRA, la
     * vérification passe avant la création, pour qu'une personne étrangère ne puisse pas en fabriquer.
     */
    private function wallet(Wallets $wallets): ?Wallet
    {
        if ($this->zumra) {
            abort_unless(in_array($this->zumra->getKey(), Auth::user()->managedZumraIds(), true), 404);

            return Experience::initial() ? $wallets->existing(Wallet::ZUMRA, $this->zumra->getKey()) : $wallets->forZumra($this->zumra);
        }

        return Experience::initial() ? $wallets->existing(Wallet::PERSON, Auth::id()) : $wallets->forPerson(Auth::user());
    }
}
