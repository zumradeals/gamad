<?php

namespace App\Services;

use App\Enums\LedgerKind;
use App\Enums\NotificationKind;
use App\Enums\PayoutStatus;
use App\Exceptions\WalletException;
use App\Models\Payout;
use App\Models\User;
use App\Models\Wallet;
use App\Support\Normalizer;
use App\Support\WalletModule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reversements du Zahab Wallet (D-218) : GAMAD reverse au titulaire indiqué, par Mobile Money, tout ou partie du solde de son portefeuille.
 * La demande débite tout de suite le registre ; le super administrateur exécute le versement et le marque comme versé, ou le refuse
 * (la somme revient). Rien ne se verse tout seul : tant que le prestataire n'a pas confirmé un décaissement automatique, le versement est
 * un acte humain, tracé au journal d'administration. Le numéro est chiffré et jamais montré à un autre que son auteur et le Président.
 */
class Payouts
{
    public function __construct(
        private readonly Wallets $wallets,
        private readonly GeniusPay $provider,
        private readonly Settings $settings,
        private readonly Notifier $notifier,
    ) {}

    public function minimum(): int
    {
        return (int) $this->settings->get('wallet_payout_min');
    }

    /**
     * @throws ValidationException
     * @throws WalletException
     */
    public function request(User $user, Wallet $wallet, int $amount, ?string $operator, ?string $phone, ?string $holder): Payout
    {
        abort_unless(WalletModule::accessibleTo($user), 404);
        Gate::forUser($user)->authorize('view', $wallet);

        [$operator, $phone, $holder] = $this->destination($operator, $phone, $holder);

        $min = $this->minimum();
        if ($amount < $min) {
            throw WalletException::payoutMinimum($min);
        }

        $key = 'payout:'.$user->getKey();
        if (RateLimiter::tooManyAttempts($key, (int) config('gamad.limits.payouts_per_day', 5))) {
            throw WalletException::payoutTooMany();
        }
        RateLimiter::hit($key, 86400);

        return DB::transaction(function () use ($user, $wallet, $amount, $operator, $phone, $holder) {
            $id = (string) Str::uuid7();
            $entry = $this->wallets->post($wallet, LedgerKind::Payout, $amount, 'payout:'.$id, $user);

            $payout = new Payout;
            $payout->forceFill([
                'id' => $id, 'wallet_id' => $wallet->getKey(), 'requested_by' => $user->getKey(), 'amount' => $amount, 'operator' => $operator,
                'phone' => $phone, 'holder_name' => $holder, 'status' => PayoutStatus::Requested, 'environment' => $this->provider->mode(),
                'debit_entry_id' => $entry->getKey(),
            ])->save();

            return $payout;
        });
    }

    /** Le titulaire (ou, pour une ZUMRA, un responsable) renonce à une demande que le Président n'a pas encore traitée. */
    public function cancel(User $user, Payout $payout): void
    {
        abort_unless(WalletModule::accessibleTo($user), 404);

        DB::transaction(function () use ($user, $payout) {
            $locked = Payout::query()->lockForUpdate()->findOrFail($payout->getKey());
            Gate::forUser($user)->authorize('view', $locked->wallet);

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['payout' => 'Cette demande a déjà été traitée.']);
            }

            $this->giveBack($locked, PayoutStatus::Cancelled, $user);
        });
    }

    /** Le Président a versé la somme au titulaire indiqué : il le consigne, avec la référence du versement. */
    public function markPaid(User $admin, Payout $payout, ?string $reference = null): void
    {
        Gate::forUser($admin)->authorize('super-admin');
        $reference = Normalizer::display((string) $reference);

        if (mb_strlen($reference) > 100) {
            throw ValidationException::withMessages(['reference' => 'La référence ne peut pas dépasser 100 caractères.']);
        }

        DB::transaction(function () use ($admin, $payout, $reference) {
            $locked = Payout::query()->lockForUpdate()->findOrFail($payout->getKey());

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['payout' => 'Cette demande a déjà été traitée.']);
            }

            $locked->forceFill(['status' => PayoutStatus::Paid, 'handled_by' => $admin->getKey(), 'handled_at' => now(), 'provider_reference' => $reference === '' ? null : $reference])->save();
            AdminJournal::record($admin, 'wallet.payout_paid', null, null, $this->payload($locked), subjectType: 'payout', subjectId: $locked->getKey());
            $this->notifier->push($locked->requester, NotificationKind::PayoutPaid, $admin);
        });
    }

    /** Le Président ne peut pas verser : il refuse, avec un motif, et la somme revient au portefeuille. */
    public function decline(User $admin, Payout $payout, ?string $reason): void
    {
        Gate::forUser($admin)->authorize('super-admin');
        $reason = Normalizer::multiline((string) $reason);

        if ($reason === '' || mb_strlen($reason) > 280) {
            throw ValidationException::withMessages(['reason' => 'Indiquez un motif de 1 à 280 caractères.']);
        }

        DB::transaction(function () use ($admin, $payout, $reason) {
            $locked = Payout::query()->lockForUpdate()->findOrFail($payout->getKey());

            if (! $locked->isOpen()) {
                throw ValidationException::withMessages(['payout' => 'Cette demande a déjà été traitée.']);
            }

            $locked->forceFill(['decline_reason' => $reason])->save();
            $this->giveBack($locked, PayoutStatus::Declined, $admin);
            AdminJournal::record($admin, 'wallet.payout_declined', null, $reason, $this->payload($locked), subjectType: 'payout', subjectId: $locked->getKey());
            $this->notifier->push($locked->requester, NotificationKind::PayoutDeclined, $admin);
        });
    }

    /** Le Président lit le numéro pour exécuter le versement : chaque lecture est inscrite au journal. */
    public function revealPhone(User $admin, Payout $payout): string
    {
        Gate::forUser($admin)->authorize('super-admin');
        AdminJournal::record($admin, 'wallet.payout_number_viewed', null, null, ['reversement' => $payout->getKey()], subjectType: 'payout', subjectId: $payout->getKey());

        return (string) $payout->phone;
    }

    /** Les sommes d'un mode : demandées en attente, versées, et rendues. */
    public function totals(string $environment): array
    {
        $rows = Payout::query()->where('environment', $environment)->selectRaw('status, count(*) as n, coalesce(sum(amount), 0) as total')->groupBy('status')->get()->keyBy(fn ($r) => $r->status->value);

        return [
            'requested' => (int) ($rows['requested']->total ?? 0), 'requested_count' => (int) ($rows['requested']->n ?? 0),
            'paid' => (int) ($rows['paid']->total ?? 0), 'paid_count' => (int) ($rows['paid']->n ?? 0),
        ];
    }

    private function giveBack(Payout $payout, PayoutStatus $status, User $actor): void
    {
        $entry = $this->wallets->post($payout->wallet, LedgerKind::PayoutBack, $payout->amount, 'payout-back:'.$payout->getKey(), $actor);
        $payout->forceFill(['status' => $status, 'return_entry_id' => $entry->getKey(), 'handled_by' => $actor->getKey(), 'handled_at' => now()])->save();
    }

    /** @return array<string, mixed> Jamais de nom ni de numéro : le type de portefeuille, la somme, le mode. */
    private function payload(Payout $payout): array
    {
        return ['zahab' => $payout->amount, 'portefeuille' => $payout->wallet->isPerson() ? 'personne' : 'ZUMRA', 'mode' => $payout->environment];
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     *
     * @throws ValidationException
     */
    private function destination(?string $operator, ?string $phone, ?string $holder): array
    {
        $errors = [];

        if (! array_key_exists((string) $operator, Payout::OPERATORS)) {
            $errors['operator'] = 'Choisissez l’opérateur du numéro.';
        }

        $number = preg_replace('/[\s.\-()]/', '', (string) $phone) ?? '';
        if (! preg_match('/^\+?\d{8,15}$/', $number)) {
            $errors['phone'] = 'Écrivez le numéro Mobile Money du titulaire, avec l’indicatif du pays (ex. +2250700000000).';
        }

        $holder = Normalizer::display((string) $holder);
        if (mb_strlen($holder) < 2 || mb_strlen($holder) > 100) {
            $errors['holder'] = 'Écrivez le nom du titulaire du numéro, tel qu’il apparaît sur son compte Mobile Money.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return [(string) $operator, $number, $holder];
    }
}
