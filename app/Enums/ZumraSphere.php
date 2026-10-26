<?php

namespace App\Enums;

/** Nature d'une ZUMRA (Z1) : un domaine d'activité social, professionnel ou religieux. */
enum ZumraSphere: string
{
    case Professional = 'professional';
    case Social = 'social';
    case Religious = 'religious';

    public function label(): string
    {
        return match ($this) {
            self::Professional => 'Professionnel',
            self::Social => 'Social',
            self::Religious => 'Religieux',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Professional => 'project',
            self::Social => 'heart',
            self::Religious => 'home',
        };
    }
}
