<?php

namespace App\Support;

/** D-183 : contrat du parcours initial, indépendant des données et des réglages de paiement. */
final class Experience
{
    public static function initial(): bool
    {
        return (bool) config('gamad.initial_experience', true);
    }
}
