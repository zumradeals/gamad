<?php

namespace App\Exceptions;

use RuntimeException;

/** Une action sur une campagne refusée par les règles d'état ; le message est destiné à la personne. */
class AdException extends RuntimeException
{
    public static function notEditable(): self
    {
        return new self('Cette campagne ne peut pas être modifiée dans son état actuel.');
    }

    public static function wrongState(string $action): self
    {
        return new self("Cette action ($action) n’est pas possible dans l’état actuel de la campagne.");
    }

    public static function tooMany(): self
    {
        return new self('Vous avez créé beaucoup de campagnes aujourd’hui. Réessayez demain.');
    }

    public static function advertiser(): self
    {
        return new self('Seules une personne ou une entreprise vérifiée que vous possédez peuvent annoncer.');
    }

    public static function ownCampaign(): self
    {
        return new self('Vous ne pouvez pas modérer votre propre annonce.');
    }
}
