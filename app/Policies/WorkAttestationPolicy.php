<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkAttestation;

class WorkAttestationPolicy
{
    public function view(User $a, WorkAttestation $v): bool
    {
        return $a->isActive() && ($a->isSuperAdmin() || in_array($a->id, [$v->author_id, $v->attester_id], true));
    }

    public function confirm(User $a, WorkAttestation $v): bool
    {
        return $a->isActive() && $a->id === $v->attester_id;
    }

    public function withdraw(User $a, WorkAttestation $v): bool
    {
        return $a->isActive() && in_array($a->id, [$v->author_id, $v->attester_id], true);
    }

    public function review(User $a, WorkAttestation $v): bool
    {
        return $a->isActive() && $a->isSuperAdmin() && ! in_array($a->id, [$v->author_id, $v->attester_id], true);
    }
}
