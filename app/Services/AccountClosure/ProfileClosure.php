<?php

namespace App\Services\AccountClosure;

use App\Core\Identity\Closure\AccountClosureParticipant;
use App\Core\Identity\Closure\ClosureContext;
use App\Models\Profile;
use App\Models\User;

/** Aucune photo ne survit au compte (données personnelles). */
class ProfileClosure implements AccountClosureParticipant
{
    public function assertCanClose(User $user): void {}

    public function beforeDelete(User $user, ClosureContext $context): void
    {
        $profile = $user->profile;

        if ($profile?->hasPhoto()) {
            $context->purgeFile(Profile::PHOTO_DISK, $profile->photo_path);
        }
    }

    public function afterDelete(User $user, ClosureContext $context): void {}
}
