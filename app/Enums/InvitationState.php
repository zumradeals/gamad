<?php

namespace App\Enums;

/** États d'une invitation (PRODUIT §4) : envoyée (en attente), acceptée, refusée, expirée — et annulée par celui qui l'a envoyée. */
enum InvitationState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Refused = 'refused';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
