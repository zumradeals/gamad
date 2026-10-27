<?php

namespace App\Enums;

/** Nature d'un mouvement du registre d'un portefeuille (D-164, D-174). */
enum LedgerKind: string
{
    case Recharge = 'recharge';
    case Contribution = 'contribution';
    case Refund = 'refund';
    case Payout = 'payout';
    case PayoutBack = 'payout_back';

    public function label(): string
    {
        return match ($this) {
            self::Recharge => 'Recharge',
            self::Contribution => 'Contribution',
            self::Refund => 'Remboursement',
            self::Payout => 'Reversement demandé',
            self::PayoutBack => 'Reversement annulé',
        };
    }

    /** Une recharge et un reversement annulé créditent le portefeuille ; une contribution, un remboursement et un reversement le débitent. */
    public function credits(): bool
    {
        return $this === self::Recharge || $this === self::PayoutBack;
    }
}
