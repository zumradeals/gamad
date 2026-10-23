<?php

namespace App\Exceptions;

use LogicException;

class ImmutableRecordException extends LogicException
{
    public static function forJournal(): self
    {
        return new self("Le journal d'administration est en ajout seul : une entrée ne peut être ni modifiée ni supprimée.");
    }

    public static function forLedger(): self
    {
        return new self('Le registre des portefeuilles est en ajout seul : un mouvement ne peut être ni modifié ni supprimé.');
    }
}
