<?php

namespace App\Enums;

/** Les quatre formes de contribution (C1, D-207) : financière, en compétences, en temps et service, matérielle. */
enum HelpKind: string
{
    case Financial = 'financial';
    case Skills = 'skills';
    case Time = 'time';
    case Material = 'material';

    public function label(): string
    {
        return match ($this) {
            self::Financial => 'Financière',
            self::Skills => 'Compétences',
            self::Time => 'Temps et service',
            self::Material => 'Matérielle',
        };
    }

    /** Phrase d'invitation du formulaire : « Comment souhaitez-vous contribuer ? ». */
    public function pitch(): string
    {
        return match ($this) {
            self::Financial => 'Soutenir avec une somme d’argent',
            self::Skills => 'Apporter votre savoir-faire',
            self::Time => 'Donner du temps ou rendre un service',
            self::Material => 'Fournir un équipement ou une ressource',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Financial => 'coins',
            self::Skills => 'group',
            self::Time => 'clock',
            self::Material => 'box',
        };
    }

    /** Classes Tailwind du badge (jetons de DESIGN.md §1). */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Financial => 'bg-type-offer-bg text-type-offer-fg',
            self::Skills => 'bg-type-learning-bg text-type-learning-fg',
            self::Time => 'bg-type-knowledge-bg text-type-knowledge-fg',
            self::Material => 'bg-type-call-bg text-type-call-fg',
        };
    }

    /** Une intention financière n'est ni payée ni encaissée dans GAMAD (C1). */
    public function disclaimer(): ?string
    {
        return $this === self::Financial ? 'Intention de soutien : rien n’est payé ni encaissé dans GAMAD.' : null;
    }
}
