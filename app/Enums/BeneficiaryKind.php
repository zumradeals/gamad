<?php

namespace App\Enums;

/** À qui l'on contribue (C1) : GAMAD, une ZUMRA, un projet, une personne ou une entreprise. */
enum BeneficiaryKind: string
{
    case Gamad = 'gamad';
    case Zumra = 'zumra';
    case Project = 'project';
    case Person = 'person';
    case Company = 'company';

    public function label(): string
    {
        return match ($this) {
            self::Gamad => 'GAMAD',
            self::Zumra => 'ZUMRA',
            self::Project => 'Projet',
            self::Person => 'Personne',
            self::Company => 'Entreprise',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Gamad => 'home',
            self::Zumra => 'community',
            self::Project => 'project',
            self::Person => 'group',
            self::Company => 'project',
        };
    }
}
