<?php

namespace App\Core\Identity\Closure;

use RuntimeException;

/** La fermeture du compte est refusée avant toute écriture ; le message est destiné à la personne. */
class AccountClosureRefused extends RuntimeException {}
