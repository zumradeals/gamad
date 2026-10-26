<?php

namespace App\Enums;

/** États d'une campagne (D-212). « Active » est calculé : une campagne Prête qui est en cours de diffusion, diffusion payante ouverte. */
enum AdState: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Refused = 'refused';
    case Ready = 'ready';
    case Active = 'active';
    case Paused = 'paused';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Brouillon',
            self::Review => 'En examen',
            self::Refused => 'Refusée',
            self::Ready => 'Prête',
            self::Active => 'Active',
            self::Paused => 'En pause',
            self::Done => 'Terminée',
        };
    }

    /** Classe de la pastille de l'état, selon la maquette validée. */
    public function badge(): string
    {
        return match ($this) {
            self::Draft, self::Done => 'bg-line-soft text-muted',
            self::Review => 'bg-[#dcebf8] text-[#0a4c85]',
            self::Refused => 'bg-type-need-bg text-type-need-fg',
            self::Ready => 'bg-type-call-bg text-type-call-fg',
            self::Active => 'bg-type-knowledge-bg text-type-knowledge-fg',
            self::Paused => 'bg-type-project-bg text-type-project-fg',
        };
    }
}
