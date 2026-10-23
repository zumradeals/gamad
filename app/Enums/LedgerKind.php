<?php

namespace App\Enums;

/** Nature d'un mouvement du registre d'un portefeuille (D-164, D-174). */
enum LedgerKind: string
{
    case Recharge = 'recharge';
    case Contribution = 'contribution';
    case Refund = 'refund';

    public function label(): string
    {
        return match ($this) {
            self::Recharge => 'Recharge',
            self::Contribution => 'Contribution',
            self::Refund => 'Remboursement',
        };
    }

    /** Une recharge crédite le portefeuille ; une contribution et un remboursement le débitent. */
    public function credits(): bool
    {
        return $this === self::Recharge;
    }
}
