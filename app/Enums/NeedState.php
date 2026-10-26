<?php

namespace App\Enums;

/** États d'un besoin (D-083) : ouvert, en cours dès qu'un geste est accepté, puis résolu ou fermé sans suite par son auteur seul. */
enum NeedState: string
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case Resolved = 'resolved';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Ouvert',
            self::InProgress => 'En cours',
            self::Resolved => 'Résolu',
            self::Closed => 'Fermé sans suite',
        };
    }

    /** Les mêmes états, dans le vocabulaire du module Contributions (C1) : publié, en cours de réponse, satisfait, clôturé. */
    public function contributionLabel(): string
    {
        return match ($this) {
            self::Open => 'Publié',
            self::InProgress => 'En cours de réponse',
            self::Resolved => 'Satisfait',
            self::Closed => 'Clôturé',
        };
    }

    /** Un besoin résolu ou fermé ne reçoit plus de geste ni de décision. */
    public function isActive(): bool
    {
        return $this === self::Open || $this === self::InProgress;
    }
}
