<?php

namespace App\Exceptions;

use RuntimeException;

/** Un mouvement ou une suppression refusés par les règles des portefeuilles ; le message est destiné à la personne. */
class WalletException extends RuntimeException
{
    public static function insufficient(): self
    {
        return new self('Le solde est insuffisant.');
    }

    public static function ceiling(int $max): self
    {
        return new self('Ce portefeuille ne peut pas dépasser '.number_format($max, 0, ',', ' ').' ZAHAB.');
    }

    public static function closed(): self
    {
        return new self('Ce portefeuille est fermé.');
    }

    public static function invalidAmount(): self
    {
        return new self('Le montant doit être un nombre entier de ZAHAB, supérieur à zéro.');
    }

    public static function payoutMinimum(int $min): self
    {
        return new self('Un reversement est d’au moins '.number_format($min, 0, ',', ' ').' ZAHAB.');
    }

    public static function payoutTooMany(): self
    {
        return new self('Vous avez atteint la limite de demandes de reversement pour aujourd’hui.');
    }

    public static function notEmpty(): self
    {
        return new self('Votre portefeuille contient encore des ZAHAB : demandez un reversement ou contactez le Président avant de supprimer le compte.');
    }

    public static function zumraNotEmpty(): self
    {
        return new self('Vous êtes la dernière personne d’une ZUMRA dont le portefeuille contient encore des ZAHAB : transmettez la ZUMRA à une autre personne avant de supprimer votre compte.');
    }
}
