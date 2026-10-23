<?php

namespace App\Services;

use App\Enums\LedgerKind;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentException;
use App\Exceptions\WalletException;
use App\Models\Contribution;
use App\Models\LedgerEntry;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Recharge d'un portefeuille par GeniusPay (Lot 6b, D-169). Règle d'or : un portefeuille n'est crédité que si le prestataire
 * confirme le paiement lorsque GAMAD le lui demande (le contenu d'un webhook ne sert qu'à retrouver le paiement), pour le montant
 * demandé, dans le mode configuré, et une seule fois.
 */
class Payments
{
    public function __construct(
        private readonly Wallets $wallets,
        private readonly GeniusPay $provider,
        private readonly Settings $settings,
        private readonly Contributions $contributions,
    ) {}

    /** La recharge n'est offerte que si les portefeuilles sont ouverts et les clés du mode courant renseignées. */
    public function available(): bool
    {
        return $this->wallets->enabled() && $this->provider->configured();
    }

    public function limits(): array
    {
        return [(int) $this->settings->get('wallet_recharge_min'), (int) $this->settings->get('wallet_recharge_max')];
    }

    /**
     * Lance un paiement : crée la ligne, demande le lien de paiement et retourne la ligne avec son adresse de paiement. Une
     * recharge crédite le portefeuille ; une contribution directe (Lot 6c) va au fonds de contribution sans toucher au portefeuille.
     *
     * @param  array{0: string, 1: ?string}  $target  orientation d'une contribution directe
     */
    public function start(User $user, Wallet $wallet, int $amount, string $successUrl, string $errorUrl, string $purpose = 'recharge', array $target = ['fund', null]): Payment
    {
        if (! $this->available() || $wallet->isClosed()) {
            throw PaymentException::unavailable();
        }

        $contribution = $purpose === 'contribution';
        [$min, $max] = $this->limits();
        $min = $contribution ? $this->contributions->minimum() : $min;

        if ($amount < $min || $amount > $max) {
            throw PaymentException::amount($min, $max);
        }

        if ($contribution) {
            $target = $this->contributions->validTarget($target);
        } else {
            $room = $this->wallets->ceilingFor($wallet) - $this->wallets->balance($wallet);

            if ($amount > $room) {
                throw PaymentException::ceiling(max($room, 0));
            }
        }

        $key = 'recharge:'.$user->getKey();
        $limit = (int) config('gamad.limits.recharges_per_hour');

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            throw PaymentException::tooMany();
        }
        RateLimiter::hit($key, 3600);

        $payment = Payment::query()->create([
            'wallet_id' => $wallet->getKey(),
            'initiated_by' => $user->getKey(),
            'amount' => $amount,
            'environment' => $this->provider->mode(),
            'status' => PaymentStatus::Pending,
            'purpose' => $purpose,
            'target_type' => $target[0],
            'target_id' => $target[1],
        ]);

        try {
            $created = $this->provider->create($payment, $contribution ? 'Contribution GAMAD' : 'Recharge ZAHAB', $successUrl.'?paiement='.$payment->getKey(), $errorUrl.'?paiement='.$payment->getKey());
        } catch (PaymentException $e) {
            $payment->forceFill(['status' => PaymentStatus::Failed])->save();

            throw $e;
        }

        $payment->forceFill(['provider_reference' => $created['reference'], 'checkout_url' => $created['checkout_url']])->save();

        return $payment;
    }

    /**
     * Demande l'état du paiement au prestataire et l'applique. Sans effet sur un paiement déjà réglé.
     *
     * @throws PaymentException si le prestataire ne répond pas
     */
    public function reconcile(Payment $payment): Payment
    {
        if (! $payment->provider_reference) {
            return $this->expireIfOld($payment);
        }

        $data = $this->provider->fetch($payment->provider_reference);

        return $this->apply($payment, (string) ($data['status'] ?? ''), (int) ($data['amount'] ?? 0), (string) ($data['environment'] ?? $payment->environment));
    }

    /** Rapproche tous les paiements encore en attente ; retourne le nombre de paiements dont l'état a changé. */
    public function reconcilePending(): int
    {
        $changed = 0;

        // Les paiements en attente, et ceux payés mais pas encore crédités (plafond, portefeuille fermé) : le crédit est retenté.
        $open = Payment::query()->where('environment', $this->provider->mode())->whereNotNull('provider_reference')
            ->where(fn ($q) => $q->where('status', PaymentStatus::Pending->value)->orWhere(fn ($q) => $q->where('status', PaymentStatus::Completed->value)->where('purpose', 'recharge')->whereNull('credit_entry_id')));

        foreach ($open->oldest()->limit(200)->get() as $payment) {
            try {
                $before = [$payment->status, $payment->credit_entry_id];
                $after = $this->reconcile($payment);

                if ([$after->status, $after->credit_entry_id] !== $before) {
                    $changed++;
                }
            } catch (PaymentException) {
                // Le prestataire ne répond pas : le paiement reste en attente, le prochain rapprochement le reprendra.
            }
        }

        return $changed;
    }

    /**
     * Applique l'état annoncé par le prestataire, sous verrou, une seule fois par mouvement.
     */
    public function apply(Payment $payment, string $providerStatus, int $providerAmount, string $providerEnvironment): Payment
    {
        return DB::transaction(function () use ($payment, $providerStatus, $providerAmount, $providerEnvironment) {
            $locked = Payment::query()->lockForUpdate()->findOrFail($payment->getKey());

            if ($providerEnvironment !== $locked->environment) {
                return $this->persist($locked, ['note' => 'Réponse d’un autre mode que celui du paiement : ignorée.']);
            }

            return match ($providerStatus) {
                'completed' => $this->credit($locked, $providerAmount),
                'refunded' => $this->refund($locked),
                'failed' => $this->close($locked, PaymentStatus::Failed),
                'cancelled' => $this->close($locked, PaymentStatus::Cancelled),
                default => $this->expireIfOld($locked),
            };
        });
    }

    /** Le total des recharges d'un mode, nettes des remboursements, face à ce que le registre a crédité (rapprochement). */
    public function reconciliation(string $environment): array
    {
        $paid = Payment::query()->where('environment', $environment)->where('purpose', 'recharge')->whereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Refunded->value]);
        $credited = (int) (clone $paid)->whereNotNull('credit_entry_id')->sum('amount');
        $refunded = (int) (clone $paid)->whereNotNull('refund_entry_id')->sum('amount');
        $ledger = (int) LedgerEntry::query()->where('kind', LedgerKind::Recharge->value)->whereIn('reference', Payment::query()->where('environment', $environment)->where('purpose', 'recharge')->whereNotNull('provider_reference')->select('provider_reference'))->sum('amount');
        $ledgerRefunds = -(int) LedgerEntry::query()->where('kind', LedgerKind::Refund->value)->whereIn('reference', Payment::query()->where('environment', $environment)->where('purpose', 'recharge')->whereNotNull('provider_reference')->select(DB::raw("'refund:' || provider_reference")))->sum('amount');

        return [
            'confirmed' => (int) (clone $paid)->sum('amount'),
            'credited' => $credited,
            'ledger' => $ledger,
            'refunded' => $refunded,
            'ledger_refunds' => $ledgerRefunds,
            'pending' => Payment::query()->where('environment', $environment)->where('status', PaymentStatus::Pending->value)->count(),
            'contributions' => (int) Payment::query()->where('environment', $environment)->where('purpose', 'contribution')->where('status', PaymentStatus::Completed->value)->sum('amount'),
            'contributions_recorded' => (int) Contribution::query()->where('environment', $environment)->where('source', 'direct')->where('status', Contribution::RECORDED)->sum('amount'),
            'to_review' => Payment::query()->where('environment', $environment)->whereNotNull('note')->count(),
            'balanced' => $credited === $ledger && $refunded === $ledgerRefunds,
        ];
    }

    /** Total des ZAHAB encore dans les portefeuilles quand des recharges d'essai ont eu lieu (0 sinon). */
    public function sandboxResidue(): int
    {
        if (! Payment::query()->where('environment', 'sandbox')->whereNotNull('credit_entry_id')->exists()) {
            return 0;
        }

        return $this->totalBalance();
    }

    public function hasLivePayments(): bool
    {
        return Payment::query()->where('environment', 'live')->where(fn ($q) => $q->whereNotNull('credit_entry_id')->orWhereIn('status', [PaymentStatus::Completed->value, PaymentStatus::Refunded->value]))->exists();
    }

    /** Somme des soldes de tous les portefeuilles : le solde de chacun est celui de son dernier mouvement. */
    public function totalBalance(): int
    {
        return (int) DB::table('ledger_entries as e')
            ->whereRaw('e.sequence = (select max(m.sequence) from ledger_entries m where m.wallet_id = e.wallet_id)')
            ->sum('e.balance_after');
    }

    /**
     * Fin des essais : retire des portefeuilles tous les ZAHAB d'essai, un mouvement par portefeuille. Refusé dès qu'un paiement réel
     * existe. Retourne [nombre de portefeuilles, ZAHAB retirés].
     *
     * @return array{0: int, 1: int}
     */
    public function endSandbox(User $actor): array
    {
        if ($this->provider->mode() !== 'sandbox' || $this->hasLivePayments()) {
            throw PaymentException::unavailable();
        }

        $count = 0;
        $total = 0;

        foreach (Wallet::query()->get() as $wallet) {
            $balance = $this->wallets->balance($wallet);

            if ($balance > 0) {
                $this->wallets->post($wallet, LedgerKind::Refund, $balance, 'fin-des-essais:'.Str::uuid(), $actor);
                $count++;
                $total += $balance;
            }
        }

        AdminJournal::record($actor, 'wallet.sandbox_cleared', payload: ['portefeuilles' => $count, 'zahab' => $total]);

        return [$count, $total];
    }

    private function credit(Payment $payment, int $providerAmount): Payment
    {
        if ($payment->purpose === 'contribution') {
            return $this->recordContribution($payment, $providerAmount);
        }

        if ($payment->credit_entry_id !== null) {
            return $payment;
        }

        if ($providerAmount !== $payment->amount) {
            return $this->persist($payment, ['note' => 'Le prestataire confirme '.$providerAmount.' au lieu de '.$payment->amount.' : rien n’a été crédité.']);
        }

        try {
            $entry = $this->wallets->post($payment->wallet, LedgerKind::Recharge, $payment->amount, $payment->provider_reference, User::query()->find($payment->initiated_by));
        } catch (WalletException $e) {
            return $this->persist($payment, ['status' => PaymentStatus::Completed, 'completed_at' => $payment->completed_at ?? now(), 'note' => 'Payée mais non créditée : '.$e->getMessage()]);
        }

        return $this->persist($payment, ['status' => PaymentStatus::Completed, 'credit_entry_id' => $entry->getKey(), 'completed_at' => now(), 'note' => null]);
    }

    /** Contribution directe confirmée : elle entre au fonds de contribution, jamais au portefeuille (D-165). */
    private function recordContribution(Payment $payment, int $providerAmount): Payment
    {
        if ($payment->contribution_id !== null) {
            return $payment;
        }

        if ($providerAmount !== $payment->amount) {
            return $this->persist($payment, ['note' => 'Le prestataire confirme '.$providerAmount.' au lieu de '.$payment->amount.' : rien n’a été enregistré.']);
        }

        $contribution = $this->contributions->recordDirect($payment);

        return $this->persist($payment, ['status' => PaymentStatus::Completed, 'contribution_id' => $contribution->getKey(), 'completed_at' => now(), 'note' => null]);
    }

    private function refund(Payment $payment): Payment
    {
        if ($payment->status === PaymentStatus::Refunded) {
            return $payment;
        }

        if ($payment->purpose === 'contribution') {
            $this->contributions->refundDirect($payment);

            return $this->persist($payment, ['status' => PaymentStatus::Refunded, 'note' => null]);
        }

        if ($payment->credit_entry_id === null) {
            return $this->persist($payment, ['status' => PaymentStatus::Refunded, 'note' => 'Remboursée par le prestataire avant d’avoir été créditée.']);
        }

        try {
            $entry = $this->wallets->post($payment->wallet, LedgerKind::Refund, $payment->amount, 'refund:'.$payment->provider_reference);
        } catch (WalletException) {
            return $this->persist($payment, ['status' => PaymentStatus::Refunded, 'note' => 'Remboursée par le prestataire, mais le solde ne permet pas de la débiter : à traiter.']);
        }

        return $this->persist($payment, ['status' => PaymentStatus::Refunded, 'refund_entry_id' => $entry->getKey(), 'note' => null]);
    }

    private function close(Payment $payment, PaymentStatus $status): Payment
    {
        // Un paiement déjà crédité ne se referme pas : seul un remboursement le défait.
        return $payment->isPending() ? $this->persist($payment, ['status' => $status]) : $payment;
    }

    /** @param  array<string, mixed>  $attributes */
    private function persist(Payment $payment, array $attributes): Payment
    {
        $payment->forceFill($attributes)->save();

        return $payment;
    }

    private function expireIfOld(Payment $payment): Payment
    {
        if ($payment->isPending() && $payment->created_at->lt(now()->subHours(Payment::LIFETIME_HOURS + 1))) {
            $payment->forceFill(['status' => PaymentStatus::Expired])->save();
        }

        return $payment;
    }
}
