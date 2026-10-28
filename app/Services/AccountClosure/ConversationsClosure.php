<?php

namespace App\Services\AccountClosure;

use App\Core\Identity\Closure\AccountClosureParticipant;
use App\Core\Identity\Closure\ClosureContext;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;

/** D-184, D-097 : la prochaine action privée et le corps des messages ne survivent pas au départ ; une conversation sans personne disparaît. */
class ConversationsClosure implements AccountClosureParticipant
{
    public function assertCanClose(User $user): void {}

    public function beforeDelete(User $user, ClosureContext $context): void
    {
        Conversation::query()->where(fn ($q) => $q->where('owner_id', $user->getKey())->orWhere('helper_id', $user->getKey()))
            ->update(['next_action' => null, 'next_action_date' => null, 'next_action_by' => null, 'next_action_confirmed_at' => null]);

        Message::query()->where('sender_id', $user->getKey())->update(['body' => null]);
    }

    public function afterDelete(User $user, ClosureContext $context): void
    {
        Conversation::query()->whereNull('owner_id')->whereNull('helper_id')->delete();
    }
}
