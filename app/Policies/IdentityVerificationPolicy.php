<?php

namespace App\Policies;

use App\Models\IdentityVerification;
use App\Models\User;

class IdentityVerificationPolicy
{
    public function view(User $a, IdentityVerification $v): bool
    {
        return $a->isActive() && ($a->id === $v->user_id || $a->isSuperAdmin());
    }

    public function review(User $a, IdentityVerification $v): bool
    {
        return $a->isActive() && $a->isSuperAdmin() && $a->id !== $v->user_id;
    }
}
