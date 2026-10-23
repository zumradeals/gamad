<?php

namespace App\Enums;

/** État d'une recharge. « En attente » est le seul état qui peut encore changer sans remboursement. */
enum PaymentStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Completed => 'Payée',
            self::Failed => 'Échouée',
            self::Cancelled => 'Annulée',
            self::Refunded => 'Remboursée',
            self::Expired => 'Expirée',
        };
    }
}
