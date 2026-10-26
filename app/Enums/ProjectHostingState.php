<?php

namespace App\Enums;

/** Accueil d'un projet par une ZUMRA (P1) : demandé, accepté, refusé ou retiré. L'accueil ne transfère ni propriété, ni direction, ni ressources. */
enum ProjectHostingState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Accueil demandé',
            self::Accepted => 'Accueilli',
            self::Declined => 'Accueil refusé',
            self::Withdrawn => 'Demande retirée',
        };
    }
}
