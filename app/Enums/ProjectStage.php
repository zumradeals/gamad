<?php

namespace App\Enums;

/** Étape de vie d'un projet (P1) : de l'idée à l'activité. Indépendante de l'état (en cours, terminé, abandonné), qui reste celui de D-127. */
enum ProjectStage: string
{
    case Idea = 'idea';
    case Preparation = 'preparation';
    case Development = 'development';
    case Launch = 'launch';
    case Activity = 'activity';

    public function label(): string
    {
        return match ($this) {
            self::Idea => 'Idée',
            self::Preparation => 'Préparation',
            self::Development => 'Développement',
            self::Launch => 'Lancement',
            self::Activity => 'Activité',
        };
    }

    /** Phrase d'état du projet, affichée dans la présentation publique. */
    public function sentence(): string
    {
        return match ($this) {
            self::Idea => 'Le projet est encore une idée à préciser.',
            self::Preparation => 'Le projet est en phase de préparation.',
            self::Development => 'Le projet est en phase de mise en place et de structuration.',
            self::Launch => 'Le projet est en phase de lancement.',
            self::Activity => 'Le projet est en activité.',
        };
    }

    /** Position dans la progression (0 à 4). */
    public function rank(): int
    {
        return array_search($this, self::cases(), true);
    }
}
