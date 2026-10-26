<?php

namespace App\Exceptions;

use RuntimeException;

/** Une entreprise a consulté toutes les pages permises aujourd'hui dans la découverte de personnes. */
class DiscoveryLimitReached extends RuntimeException {}
