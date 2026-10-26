<?php

namespace App\Enums;

/**
 * Postes de gouvernance recommandés d'une ZUMRA, en plus du responsable principal (le fondateur). Aucun n'est obligatoire ; ils ne
 * donnent aucun privilège automatique, notamment financier (Z1, D-205).
 */
enum ZumraDuty: string
{
    case FirstDeputy = 'first_deputy';
    case SecondDeputy = 'second_deputy';
    case Finance = 'finance';
    case SocialReligious = 'social_religious';

    public function label(): string
    {
        return match ($this) {
            self::FirstDeputy => 'Premier responsable adjoint',
            self::SecondDeputy => 'Deuxième responsable adjoint',
            self::Finance => 'Responsable financier',
            self::SocialReligious => 'Responsable des affaires sociales et religieuses',
        };
    }
}
