<?php

namespace App\Policies;

use App\Models\TrustReport;
use App\Models\User;

class TrustReportPolicy
{
    public function view(User $a, TrustReport $r): bool
    {
        return $a->isActive() && ($a->id === $r->reporter_id || ($a->isSuperAdmin() && ! in_array($a->id, [$r->subject_id, $r->workAttestation?->attester_id], true)));
    }

    public function review(User $a, TrustReport $r): bool
    {
        return $a->isActive() && $a->isSuperAdmin() && ! in_array($a->id, [$r->reporter_id, $r->subject_id, $r->workAttestation?->attester_id], true);
    }
}
