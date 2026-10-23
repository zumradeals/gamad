<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Auth\Access\Response;

/**
 * Un portefeuille est privé (D-170) : sa personne seule, ou les responsables de sa ZUMRA. Ni le Président ni la modération ne le
 * lisent. Pour tout autre, il n'existe pas (404).
 */
class WalletPolicy
{
    public function view(?User $user, Wallet $wallet): Response
    {
        if (! $user || ! $user->hasVerifiedEmail()) {
            return Response::denyAsNotFound();
        }

        $allowed = $wallet->isPerson()
            ? $wallet->owner_id === $user->getKey()
            : in_array($wallet->owner_id, $user->managedZumraIds(), true);

        return $allowed ? Response::allow() : Response::denyAsNotFound();
    }
}
