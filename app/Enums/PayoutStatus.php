<?php

namespace App\Enums;

/** État d'un reversement du Zahab Wallet (D-218) : demandé, versé par le Président, refusé (la somme revient), ou annulé par son titulaire. */
enum PayoutStatus: string
{
    case Requested = 'requested';
    case Paid = 'paid';
    case Declined = 'declined';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Demandé',
            self::Paid => 'Versé',
            self::Declined => 'Refusé',
            self::Cancelled => 'Annulé',
        };
    }
}
