<?php

namespace App\Core\Identity;

use Illuminate\Support\Facades\DB;

class SecurityRetention
{
    /** Bounded, resumable deletion. No incident holds in V1: no indefinite exception to retention. */
    public function purge(int $batch = 2000): array
    {
        $batch = max(1, min(10000, $batch));
        $ids = DB::table('security_access_events')->where('occurred_at', '<', now()->subDays(max(1, (int) config('core_console.security.detail_days'))))
            ->orderBy('occurred_at')->limit($batch)->pluck('id');
        $details = DB::table('security_access_events')->whereIn('id', $ids)->delete();
        $hours = DB::table('security_access_hours')->where('hour', '<', now()->startOfHour()->subDays(max(1, (int) config('core_console.security.aggregate_days'))))->delete();

        return ['details' => $details, 'hours' => $hours];
    }
}
