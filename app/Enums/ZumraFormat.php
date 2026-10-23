<?php

namespace App\Enums;

/** Format d'une ZUMRA : où ses membres se retrouvent (maquettes Carrefour et Monde). */
enum ZumraFormat: string
{
    case Online = 'online';
    case Onsite = 'onsite';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Online => 'En ligne',
            self::Onsite => 'Présentiel',
            self::Hybrid => 'Hybride',
        };
    }
}
