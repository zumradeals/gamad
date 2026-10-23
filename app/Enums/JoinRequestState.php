<?php

namespace App\Enums;

/** États d'une demande d'adhésion : en attente, acceptée, déclinée, retirée (D-110). */
enum JoinRequestState: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Declined = 'declined';
    case Withdrawn = 'withdrawn';
}
