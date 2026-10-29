<?php

return [
    'evidence_fresh_hours' => 24,
    'queue_degraded_seconds' => (int) env('GAMAD_QUEUE_DEGRADED_SECONDS', 300),
    // Null means no verified cadence: never infer a backup outage from an arbitrary default.
    'backup_expected_hours' => env('GAMAD_BACKUP_EXPECTED_HOURS'),
    'backup_grace_hours' => (int) env('GAMAD_BACKUP_GRACE_HOURS', 12),
    'security' => [
        'detail_days' => (int) env('GAMAD_SECURITY_DETAIL_DAYS', 30),
        'aggregate_days' => (int) env('GAMAD_SECURITY_AGGREGATE_DAYS', 90),
        'window_minutes' => 15,
        'account_failures' => 10,
        'source_failures' => 30,
        'distributed_sources' => 5,
        'multiple_targets' => 10,
        'sensitive_failures' => 3,
        'excessive_limits' => 20,
    ],
];
