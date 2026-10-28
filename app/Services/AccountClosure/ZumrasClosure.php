<?php

namespace App\Services\AccountClosure;

use App\Core\Identity\Closure\AccountClosureParticipant;
use App\Core\Identity\Closure\ClosureContext;
use App\Models\User;
use App\Models\Wallet;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Services\Memberships;
use App\Services\Wallets;

/** D-115 : la fondation passe à un autre membre ; une ZUMRA qui n'a plus personne disparaît, son portefeuille est fermé. */
class ZumrasClosure implements AccountClosureParticipant
{
    public function __construct(private readonly Memberships $memberships, private readonly Wallets $wallets) {}

    public function assertCanClose(User $user): void {}

    public function beforeDelete(User $user, ClosureContext $context): void
    {
        $context->remember('zumras', ZumraMembership::query()->where('user_id', $user->getKey())->pluck('zumra_id')->all());

        $this->memberships->passFoundationFrom($user);
    }

    public function afterDelete(User $user, ClosureContext $context): void
    {
        $empty = Zumra::query()->whereIn('id', $context->recall('zumras', []))->withoutMembers()->pluck('id');

        foreach ($empty as $id) {
            $this->wallets->closeFor(Wallet::ZUMRA, $id);
        }

        Zumra::query()->whereIn('id', $empty)->delete();
    }
}
