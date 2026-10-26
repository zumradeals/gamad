<?php

namespace App\Enums;

/** Les trois objectifs d'une campagne (D-210, maquette validée). */
enum AdObjective: string
{
    case Awareness = 'awareness';
    case Contact = 'contact';
    case Visit = 'visit';

    public function label(): string
    {
        return match ($this) {
            self::Awareness => 'Faire connaître',
            self::Contact => 'Recevoir des contacts',
            self::Visit => 'Faire visiter',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Awareness => 'Votre annonce est vue par un maximum de personnes pertinentes.',
            self::Contact => 'Des personnes vous écrivent depuis l’annonce.',
            self::Visit => 'Les personnes ouvrent votre page, votre profil ou un lien.',
        };
    }

    /** Boutons d'annonce permis pour cet objectif. @return array<int, AdCta> */
    public function ctas(): array
    {
        return match ($this) {
            self::Awareness => [AdCta::Profile, AdCta::Link],
            self::Contact => [AdCta::Exchange],
            self::Visit => [AdCta::Profile, AdCta::Link],
        };
    }
}
