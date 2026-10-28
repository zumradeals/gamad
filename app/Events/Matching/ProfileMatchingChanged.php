<?php

namespace App\Events\Matching;

use App\Models\User;

/** Fait survenu dans GAMAD Social qui concerne le Matching. Voir App\Listeners\MatchingSubscriber. */
final class ProfileMatchingChanged
{
    public function __construct(public User $user, public bool $revoked) {}
}
