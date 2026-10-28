<?php

namespace App\Core\Identity;

use Carbon\CarbonImmutable;

/** Une session ouverte, telle que la personne la voit. `handle` est opaque : l'identifiant réel de la session n'est jamais exposé. */
final readonly class SessionInfo
{
    public function __construct(
        public string $handle,
        public string $browser,
        public string $platform,
        public ?string $ip,
        public CarbonImmutable $lastActivity,
        public bool $current,
    ) {}
}
