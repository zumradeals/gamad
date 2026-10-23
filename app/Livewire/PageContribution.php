<?php

namespace App\Livewire;

use App\Enums\PaymentStatus;
use App\Exceptions\ContributionException;
use App\Exceptions\PaymentException;
use App\Models\Contribution;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Wallet;
use App\Models\Zumra;
use App\Services\Contributions;
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
 * Contribution mensuelle facultative (Lot 6c, D-163, D-166) : la personne, ou les responsables d'une ZUMRA, contribuent depuis le
 * portefeuille ou en direct par Mobile Money, orientent leur contribution, demandent un rappel mensuel et lisent leur rapport
 * d'impact. Les montants ne sont visibles que de leur auteur et du Président (D-170).
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Contribution')]
class PageContribution extends Component
{
    use WithPagination;

    public ?Zumra $zumra = null;

    public string $amount = '';

    /** « wallet » (depuis le portefeuille) ou « direct » (Mobile Money). */
    public string $source = 'wallet';

    /** « fund », « zumra:<id> » ou « project:<id> ». */
    public string $orientation = 'fund';

    public ?string $message = null;

    #[Url(as: 'paiement')]
    public ?string $returned = null;

    public function mount(Wallets $wallets, Payments $payments, ?Zumra $zumra = null): void
    {
        $this->zumra = $zumra;
        $wallet = $this->wallet($wallets);
        Gate::authorize('view', $wallet);

        if ($this->returned !== null) {
            $this->refresh($payments, $wallet);
        }
    }

    public function contribute(Wallets $wallets, Payments $payments, Contributions $contributions): void
    {
        $wallet = $this->wallet($wallets);
        Gate::authorize('view', $wallet);
        $this->resetErrorBag();
        $this->reset('message');

        $this->validate(['amount' => ['required', 'integer'], 'source' => ['required', 'in:wallet,direct']], [], ['amount' => 'montant']);

        try {
            $target = $this->target();

            if ($this->source === 'direct') {
                $back = $this->zumra ? route('zumras.contribution', $this->zumra) : route('contribution');
                $payment = $payments->start(Auth::user(), $wallet, (int) $this->amount, $back, $back, 'contribution', $target);
                $this->redirect($payment->checkout_url);

                return;
            }

            $contribution = $contributions->fromWallet(Auth::user(), $wallet, (int) $this->amount, $target);
        } catch (ContributionException|PaymentException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }

        $this->message = $contribution->isAwaiting()
            ? 'Votre demande attend la validation du Président : une dépense de ZUMRA au-delà de '.number_format($contributions->threshold(), 0, ',', ' ').' ZAHAB doit être validée. Rien n’est débité avant.'
            : 'Merci : votre contribution est enregistrée.';
        $this->reset('amount');
    }

    /** Rappel mensuel et badge : de simples choix du titulaire. */
    public function setReminder(Wallets $wallets, bool $on): void
    {
        $wallet = $this->wallet($wallets);
        Gate::authorize('view', $wallet);

        $wallet->forceFill(['monthly_reminder' => $on])->save();
    }

    public function setBadge(Wallets $wallets, bool $on): void
    {
        $wallet = $this->wallet($wallets);
        Gate::authorize('view', $wallet);

        if ($wallet->isPerson()) {
            $wallet->forceFill(['show_badge' => $on])->save();
        }
    }

    public function withdraw(Wallets $wallets, Contributions $contributions, string $id): void
    {
        $wallet = $this->wallet($wallets);
        Gate::authorize('view', $wallet);

        $contribution = Contribution::query()->where('wallet_id', $wallet->getKey())->findOrFail($id);

        try {
            $contributions->withdraw($contribution);
            $this->message = 'Demande retirée.';
        } catch (ContributionException $e) {
            $this->addError('amount', $e->getMessage());
        }
    }

    public function refresh(Payments $payments, ?Wallet $wallet = null): void
    {
        $wallet ??= $this->wallet(app(Wallets::class));

        foreach (Payment::query()->where('wallet_id', $wallet->getKey())->where('purpose', 'contribution')->where('status', PaymentStatus::Pending->value)->whereNotNull('provider_reference')->latest()->limit(5)->get() as $payment) {
            try {
                $payments->reconcile($payment);
            } catch (PaymentException) {
                // Le prestataire ne répond pas : la page réessaiera.
            }
        }
    }

    public function render(Wallets $wallets, Payments $payments, Contributions $contributions)
    {
        $wallet = $this->wallet($wallets);
        Gate::authorize('view', $wallet);

        $engagedZumras = Zumra::query()->whereIn('id', $contributions->engagedZumraIds())->orderBy('name')->limit(50)->get(['id', 'name']);
        $last = $contributions->lastContributionAt($wallet);

        return view('livewire.page-contribution', [
            'wallet' => $wallet,
            'balance' => $wallets->balance($wallet),
            'engaged' => $this->zumra ? $contributions->isEngaged($this->zumra) : $contributions->isContributor(Auth::user()),
            'engagedUntil' => $last?->copy()->addDays($contributions->engagementDays()),
            'impact' => $contributions->impact($wallet),
            'history' => Contribution::query()->where('wallet_id', $wallet->getKey())->latest()->paginate(10),
            'zumraOptions' => $engagedZumras,
            'projectOptions' => Project::query()->whereIn('zumra_id', $engagedZumras->pluck('id'))->orderBy('name')->limit(100)->get(['id', 'name', 'zumra_id']),
            'pending' => Payment::query()->where('wallet_id', $wallet->getKey())->where('purpose', 'contribution')->where('status', PaymentStatus::Pending->value)->where('created_at', '>', now()->subHours(Payment::LIFETIME_HOURS))->latest()->get(),
            'returnedPayment' => $this->returned ? Payment::query()->where('wallet_id', $wallet->getKey())->where('purpose', 'contribution')->find($this->returned) : null,
            'directAvailable' => $payments->available(),
            'minimum' => $contributions->minimum(),
            'days' => $contributions->engagementDays(),
            'sandbox' => $contributions->environment() === 'sandbox',
        ]);
    }

    /** @return array{0: string, 1: ?string} */
    private function target(): array
    {
        if ($this->orientation === 'fund') {
            return [Contribution::FUND, null];
        }

        [$type, $id] = array_pad(explode(':', $this->orientation, 2), 2, null);

        return [(string) $type, $id];
    }

    private function wallet(Wallets $wallets): Wallet
    {
        if ($this->zumra) {
            abort_unless(in_array($this->zumra->getKey(), Auth::user()->managedZumraIds(), true), 404);

            return $wallets->forZumra($this->zumra);
        }

        return $wallets->forPerson(Auth::user());
    }
}
