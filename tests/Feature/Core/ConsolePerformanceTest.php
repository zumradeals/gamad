<?php

use App\Core\Identity\IdentityObservations;
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

it('observe Identity sur 100000 comptes, sessions et événements avec des agrégats bornés', function () {
    // Include the exact seven-day boundary deterministically, independently of fixture creation time.
    $this->travelTo(now()->startOfSecond());
    $eventAnchor = now()->format('Y-m-d H:i:sP');
    config(['session.driver' => 'database', 'session.lifetime' => 120]);
    DB::statement("insert into users (id, name, email, password, name_search, email_verified_at, suspended_at, suspension_reason, created_at, updated_at)
        select md5('volume-account-' || n)::uuid, 'Compte de recette ' || n, n || '@volume.example.test', 'not-an-authentication-secret', 'compte de recette',
        case when n % 4 <> 0 then now() end, case when n % 10 = 0 then now() end, case when n % 10 = 0 then 'Suspension de recette' end,
        now() - interval '1 year', now() from generate_series(1, 100000) n");
    DB::statement("insert into sessions (id, user_id, payload, last_activity)
        select 'volume-session-' || n, md5('volume-account-' || n)::uuid, 'PRIVATE-SESSION-CONTENT', extract(epoch from now())::bigint - (n % 14400) from generate_series(1, 100000) n");
    DB::statement("insert into identity_events (id, user_id, type, occurred_at, context)
        select md5('volume-event-' || n)::uuid, md5('volume-account-' || n)::uuid,
        (array['password.changed','password.reset','session.revoked','sessions.others_revoked','account.closed'])[1 + (n % 5)],
        ?::timestamptz - ((n * 7) % 604800) * interval '1 second', '{\"private\":\"PRIVATE-EVENT-CONTENT\"}'::jsonb from generate_series(1, 100000) n", [$eventAnchor]);
    foreach (['users', 'sessions', 'identity_events'] as $table) {
        DB::statement('analyze '.$table);
    }
    $times = [];
    $memory = memory_get_usage(true);
    DB::enableQueryLog();
    for ($i = 0; $i < 3; $i++) {
        $start = hrtime(true);
        $snapshot = app(IdentityObservations::class)->snapshot(168);
        $times[] = round((hrtime(true) - $start) / 1000000, 1);
        expect($snapshot['accounts']['total'])->toBe(100000)->and($snapshot['accounts']['active'])->toBe(70000);
        expect($snapshot['accounts']['pending'])->toBe(20000)->and($snapshot['accounts']['suspended'])->toBe(10000);
        expect(array_sum(array_column($snapshot['events'], 'total')))->toBe(100000);
        expect($snapshot['sessions']['count'])->toBeGreaterThan(0)->toBeLessThan(100000);
        expect(json_encode($snapshot))->not->toContain('PRIVATE-SESSION')->not->toContain('PRIVATE-EVENT')->not->toContain('volume.example.test');
    }
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();
    $growth = memory_get_usage(true) - $memory;
    expect($queries)->toBe(12)->and($growth)->toBeLessThan(8 * 1024 * 1024)->and(max($times))->toBeLessThan(3000);
    fwrite(STDERR, "\nIdentity observation benchmark: ".json_encode(['accounts' => 100000, 'sessions' => 100000, 'events' => 100000, 'snapshot_ms' => $times, 'queries_per_snapshot' => 4, 'memory_growth_bytes' => $growth])."\n");
});
