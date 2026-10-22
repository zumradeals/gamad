<?php

namespace App\Exceptions;

use LogicException;

class ImmutableRecordException extends LogicException
{
    public static function forJournal(): self
    {
        return new self("Le journal d'administration est en ajout seul : une entrée ne peut être ni modifiée ni supprimée.");
    }
}
