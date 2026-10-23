<?php

namespace App\Enums;

/** Raisons proposées pour signaler une publication (écran 16). */
enum ReportCategory: string
{
    case Offensive = 'offensive';
    case Scam = 'scam';
    case Misinformation = 'misinformation';
    case Spam = 'spam';
    case Privacy = 'privacy';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Offensive => 'Contenu offensant ou haineux',
            self::Scam => 'Arnaque ou demande d’argent suspecte',
            self::Misinformation => 'Fausse information',
            self::Spam => 'Publicité ou spam',
            self::Privacy => 'Atteinte à la vie privée',
            self::Other => 'Autre',
        };
    }
}
