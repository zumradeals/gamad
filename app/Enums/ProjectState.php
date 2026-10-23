<?php

namespace App\Enums;

/** États d'un projet (PRODUIT §4) : idée, en cours, terminé, abandonné. Les responsables de la ZUMRA les changent (D-127). */
enum ProjectState: string
{
    case Idea = 'idea';
    case InProgress = 'in_progress';
    case Done = 'done';
    case Abandoned = 'abandoned';

    public function label(): string
    {
        return match ($this) {
            self::Idea => 'Idée',
            self::InProgress => 'En cours',
            self::Done => 'Terminé',
            self::Abandoned => 'Abandonné',
        };
    }

    /** Couleurs du badge d'état (jetons de DESIGN.md §1). */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Idea => 'bg-type-call-bg text-type-call-fg',
            self::InProgress => 'bg-type-learning-bg text-type-learning-fg',
            self::Done => 'bg-type-knowledge-bg text-type-knowledge-fg',
            self::Abandoned => 'bg-line-soft text-muted',
        };
    }

    /**
     * États vers lesquels on peut passer : une idée démarre ou est abandonnée ; un projet en cours se termine ou est abandonné ;
     * un projet terminé ou abandonné peut être repris.
     *
     * @return array<int, self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Idea => [self::InProgress, self::Abandoned],
            self::InProgress => [self::Done, self::Abandoned],
            self::Done, self::Abandoned => [self::InProgress],
        };
    }

    /** Verbe du bouton qui mène à cet état. */
    public function action(): string
    {
        return match ($this) {
            self::Idea => 'Remettre en idée',
            self::InProgress => 'Démarrer le projet',
            self::Done => 'Marquer terminé',
            self::Abandoned => 'Abandonner',
        };
    }
}
