<?php

namespace App\Enums;

/** États d'une étape de projet (PRODUIT §3) : à faire, en cours, franchie. */
enum ProjectStepState: string
{
    case Todo = 'todo';
    case InProgress = 'in_progress';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'À faire',
            self::InProgress => 'En cours',
            self::Done => 'Franchie',
        };
    }

    /** Couleurs du badge d'état (jetons de DESIGN.md §1). */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Todo => 'border border-line bg-ground text-muted',
            self::InProgress => 'bg-type-learning-bg text-type-learning-fg',
            self::Done => 'bg-type-knowledge-bg text-type-knowledge-fg',
        };
    }
}
