<?php

namespace App\Services\AccountClosure;

use App\Core\Identity\Closure\AccountClosureParticipant;
use App\Core\Identity\Closure\AccountClosureRefused;
use App\Core\Identity\Closure\ClosureContext;
use App\Exceptions\WalletException;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallets;

/** D-172 : aucun compte ne disparaît avec des ZAHAB dedans. Le portefeuille de la personne est fermé, son registre reste (D-175). */
class WalletsClosure implements AccountClosureParticipant
{
    public function __construct(private readonly Wallets $wallets) {}

    public function assertCanClose(User $user): void
    {
        try {
            $this->wallets->assertAccountCanBeDeleted($user);
        } catch (WalletException $refusal) {
            throw new AccountClosureRefused($refusal->getMessage(), previous: $refusal);
        }
    }

    public function beforeDelete(User $user, ClosureContext $context): void {}

    public function afterDelete(User $user, ClosureContext $context): void
    {
        $this->wallets->closeFor(Wallet::PERSON, $user->getKey());
    }
}
