<?php

namespace App\Enums;

/** Rôles dans une ZUMRA (D-111) : fondateur (un seul), responsable, membre. */
enum ZumraRole: string
{
    case Founder = 'founder';
    case Manager = 'manager';
    case Member = 'member';

    public function label(): string
    {
        return match ($this) {
            self::Founder => 'Fondateur',
            self::Manager => 'Responsable',
            self::Member => 'Membre',
        };
    }

    /** Le fondateur et les responsables gèrent la ZUMRA. */
    public function manages(): bool
    {
        return $this === self::Founder || $this === self::Manager;
    }
}
