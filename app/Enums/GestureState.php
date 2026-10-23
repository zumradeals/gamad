<?php

namespace App\Enums;

/** États d'un geste (PRODUIT §4) : envoyé, accepté, décliné, retiré. */
enum GestureState: string
{
    case Sent = 'sent';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Sent => 'En attente',
            self::Accepted => 'Acceptée',
            self::Declined => 'Déclinée',
            self::Withdrawn => 'Retirée',
        };
    }
}
