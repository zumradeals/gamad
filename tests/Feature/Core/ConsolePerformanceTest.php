<?php

use App\Services\CoreConsoleObservations;
use Illuminate\Support\Facades\DB;

it('calcule un tableau de bord borné sur 100000 accès et 100000 exécutions sans charger les journaux en mémoire', function () {
    // Technical fixtures only: seven days of varied traffic plus a concentrated attack, never installed in production.
    DB::statement("insert into security_access_events (id, occurred_at, outcome, source_key, target_key)
        select md5('access-' || n)::uuid, now() - (case when n > 80000 then n % 900 else (n * 7) % 604800 end) * interval '1 second',
        case when n % 5 = 0 then 'failure' else 'success' end, md5('source-' || (case when n > 80000 then n % 64 else n % 2000 end)), md5('target-' || (case when n > 80000 then 99999 else n % 4000 end)) from generate_series(1, 100000) n");
    DB::statement("insert into execution_runs (id, job, attempt, outcome, duration_ms, metrics, ran_at)
        select md5('run-' || n)::uuid, 'RunMatchingSearch', 1, 'success', 120, '{\"examined\":40,\"produced\":2}'::jsonb,
        now() - ((n * 7) % 604800) * interval '1 second' from generate_series(1, 100000) n");
    DB::statement('analyze security_access_events');
    DB::statement('analyze execution_runs');
    $times = [];
    $memory = memory_get_usage(true);
    DB::enableQueryLog();
    for ($i = 0; $i < 3; $i++) {
        $start = hrtime(true);
        $snapshot = app(CoreConsoleObservations::class)->snapshot(168);
        $times[] = round((hrtime(true) - $start) / 1000000, 1);
        expect($snapshot['measurement_errors'])->toBe([]);
        expect(count($snapshot['security']['hours']))->toBeLessThanOrEqual(168);
        expect(count($snapshot['security_incidents']))->toBeLessThanOrEqual(120);
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    $growth = memory_get_usage(true) - $memory;
    expect($queries)->toBeLessThanOrEqual(180)->and($growth)->toBeLessThan(32 * 1024 * 1024);
    // Generous CI ceiling catches unbounded correlated scans, not hardware microbenchmarks.
    expect(max($times))->toBeLessThan(10000);
    fwrite(STDERR, '\nConsole benchmark: '.json_encode(['rows' => ['security' => 100000, 'execution' => 100000], 'snapshot_ms' => $times, 'queries_per_snapshot' => $queries / 3, 'memory_growth_bytes' => $growth])."\n");
});
