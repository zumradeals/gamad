<?php

namespace App\Exceptions;

use RuntimeException;

/** Une contribution, une validation ou une affectation refusée par les règles ; le message est destiné à la personne. */
class ContributionException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self('La contribution n’est pas disponible pour l’instant.');
    }

    public static function minimum(int $min): self
    {
        return new self('La contribution est d’au moins '.number_format($min, 0, ',', ' ').' ZAHAB (1 ZAHAB = 1 FCFA).');
    }

    public static function target(): self
    {
        return new self('Cette orientation n’est pas possible : choisissez le fonds commun, une ZUMRA engagée ou un projet d’une ZUMRA engagée.');
    }

    public static function insufficient(): self
    {
        return new self('Le solde du portefeuille ne suffit pas pour cette contribution.');
    }

    public static function notPending(): self
    {
        return new self('Cette demande a déjà été traitée.');
    }

    public static function notFinanceable(): self
    {
        return new self('Ce projet n’est pas finançable : sa ZUMRA doit être engagée et reconnue par GAMAD.');
    }

    public static function exceedsFund(int $available): self
    {
        return new self('Le fonds ne contient que '.number_format($available, 0, ',', ' ').' ZAHAB à affecter.');
    }
}
