<?php

namespace App\Enums;

/** Comment on entre dans une ZUMRA (D-216) : librement et tout de suite, ou sur approbation du fondateur ou d'un responsable. */
enum ZumraJoinMode: string
{
    case Open = 'open';
    case Approval = 'approval';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Adhésion libre',
            self::Approval => 'Sur approbation',
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::Open => 'Toute personne vérifiée rejoint tout de suite, sans attendre de réponse.',
            self::Approval => 'Chaque demande est examinée par le fondateur ou un responsable.',
        };
    }
}
