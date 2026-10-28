<?php

namespace App\Events\Matching;

use App\Models\User;

/** Fait survenu dans GAMAD Social qui concerne le Matching. Voir App\Listeners\MatchingSubscriber. */
final class PersonBlockChanged
{
    public function __construct(public User $actor, public User $other, public bool $blocked) {}
}
