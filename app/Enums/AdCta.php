<?php

namespace App\Enums;

/** Le bouton de l'annonce : « Échanger » ouvre la conversation privée existante, la personne décide de répondre. */
enum AdCta: string
{
    case Exchange = 'exchange';
    case Profile = 'profile';
    case Link = 'link';

    public function label(): string
    {
        return match ($this) {
            self::Exchange => 'Échanger',
            self::Profile => 'Voir la page',
            self::Link => 'Ouvrir le lien',
        };
    }
}
