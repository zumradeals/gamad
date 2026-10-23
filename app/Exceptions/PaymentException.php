<?php

namespace App\Exceptions;

use RuntimeException;

/** Un paiement refusé par nos règles ou par le prestataire ; le message est destiné à la personne. */
class PaymentException extends RuntimeException
{
    public static function unavailable(): self
    {
        return new self('La recharge n’est pas disponible pour l’instant.');
    }

    public static function amount(int $min, int $max): self
    {
        return new self('Le montant doit être un nombre entier de ZAHAB entre '.number_format($min, 0, ',', ' ').' et '.number_format($max, 0, ',', ' ').'.');
    }

    public static function ceiling(int $room): self
    {
        return new self($room > 0
            ? 'Cette recharge dépasserait le solde maximum du portefeuille : vous pouvez encore ajouter '.number_format($room, 0, ',', ' ').' ZAHAB.'
            : 'Le portefeuille a atteint son solde maximum.');
    }

    public static function tooMany(): self
    {
        return new self('Trop de recharges lancées. Réessayez plus tard.');
    }

    public static function provider(): self
    {
        return new self('Le service de paiement ne répond pas pour l’instant. Rien n’a été débité ; réessayez dans quelques minutes.');
    }
}
