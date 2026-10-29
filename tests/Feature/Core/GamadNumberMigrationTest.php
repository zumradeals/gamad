<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;

/** Copy the existing table definitions into an isolated schema, then recreate the pre-numbering state with synthetic accounts. */
function withLegacyIdentityCopy(int $count, Closure $check): void
{
    $schema = 'identity_copy_'.bin2hex(random_bytes(5));
    $original = DB::selectOne('show search_path')->search_path;
    DB::statement("create schema $schema");
    try {
        DB::statement("set local search_path to $schema, public");
        DB::statement('create table users (like public.users including defaults including constraints)');
        DB::statement('alter table users drop column gamad_number');
        DB::statement('alter table users add primary key (id)');
        DB::statement('create unique index legacy_users_email on users (lower(email))');
        DB::statement('create table profiles (like public.profiles including defaults including constraints)');
        DB::statement('alter table profiles add primary key (id)');
        DB::statement('create unique index legacy_profiles_public on profiles (public_id)');
        DB::statement('create table identity_events (like public.identity_events including defaults including constraints)');
        DB::statement("insert into users (id, name, email, name_search, password, email_verified_at, suspended_at, suspension_reason, created_at, updated_at)
            select md5('legacy-user-' || n)::uuid, 'Compte de recette ' || n, n || '@legacy.example.test', 'compte de recette', 'non-authenticating-test-value',
            case when n % 4 <> 0 then now() - interval '1 day' end, case when n % 10 = 0 then now() end,
            case when n % 10 = 0 then 'Suspension de recette' end, now() - interval '1 year', now() - interval '1 day' from generate_series(1, ?) n", [$count]);
        DB::statement("insert into profiles (id, user_id, public_id, created_at, updated_at)
            select md5('legacy-profile-' || n)::uuid, md5('legacy-user-' || n)::uuid, lpad(n::text, 12, '0'), now(), now() from generate_series(1, ?) n", [$count]);
        $check();
    } finally {
        DB::selectOne("select set_config('search_path', ?, true)", [$original]);
        DB::statement("drop schema $schema cascade");
    }
}

it('attribue les numéros sur une copie représentative de 100000 comptes existants sans changer UUID, profils ou attributs', function () {
    withLegacyIdentityCopy(100000, function () {
        $before = DB::selectOne("select md5(string_agg(id::text || email || password || updated_at::text, '' order by id)) as checksum from users")->checksum;
        $profiles = DB::selectOne("select md5(string_agg(id::text || user_id::text || public_id, '' order by id)) as checksum from profiles")->checksum;
        $migration = require database_path('migrations/2026_12_14_100000_complete_identity_and_assign_gamad_numbers.php');
        $start = hrtime(true);
        DB::transaction(fn () => $migration->up());
        $elapsed = round((hrtime(true) - $start) / 1000000, 1);
        expect(DB::table('users')->count())->toBe(100000);
        expect(DB::table('users')->distinct()->count('gamad_number'))->toBe(100000);
        expect(DB::table('gamad_number_reservations')->count())->toBe(100000);
        expect(DB::table('users')->whereNull('gamad_number')->exists())->toBeFalse();
        expect(DB::selectOne("select md5(string_agg(id::text || email || password || updated_at::text, '' order by id)) as checksum from users")->checksum)->toBe($before);
        expect(DB::selectOne("select md5(string_agg(id::text || user_id::text || public_id, '' order by id)) as checksum from profiles")->checksum)->toBe($profiles);
        expect($elapsed)->toBeLessThan(30000);
        fwrite(STDERR, "\nGAMAD migration benchmark: ".json_encode(['existing_accounts' => 100000, 'backfill_ms' => $elapsed, 'uuid_profile_checksums_preserved' => true])."\n");
    });
});

it('annule entièrement une migration interrompue sans attribution partielle', function () {
    withLegacyIdentityCopy(10, function () {
        $migration = require database_path('migrations/2026_12_14_100000_complete_identity_and_assign_gamad_numbers.php');
        expect(function () use ($migration) {
            DB::transaction(function () use ($migration) {
                $migration->up();
                throw new RuntimeException('Interruption simulée avant validation');
            });
        })->toThrow(RuntimeException::class);
        expect(Schema::hasColumn('users', 'gamad_number'))->toBeFalse();
        expect(Schema::hasTable('gamad_number_reservations'))->toBeFalse();
        expect(DB::table('users')->count())->toBe(10)->and(DB::table('profiles')->count())->toBe(10);
    });
});

it('protège des inscriptions réellement simultanées avec collisions forcées entre huit connexions', function () {
    $config = config('database.connections.pgsql');
    $dsn = "pgsql:host={$config['host']};port={$config['port']};dbname={$config['database']}";
    // Independent committed schema: child processes cannot see the test transaction, and never write to the platform tables.
    $pdo = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $schema = 'identity_concurrent_'.bin2hex(random_bytes(5));
    $pdo->exec("create schema $schema");
    $children = [];
    try {
        $pdo->exec("create table $schema.users (like public.users including all)");
        $pdo->exec("create table $schema.gamad_number_reservations (like public.gamad_number_reservations including all)");
        $pdo->exec("create trigger users_gamad_number_guard before insert or update on $schema.users for each row execute function public.users_protect_gamad_number()");
        $pdo->exec("create function $schema.gamad_number_candidate() returns text as \$\$ begin
            if current_setting('gamad_probe.used', true) is distinct from 'yes' then
                perform set_config('gamad_probe.used', 'yes', false); return 'GMD-0000-0000-0000';
            end if;
            return public.gamad_number_candidate(); end; \$\$ language plpgsql volatile");
        for ($worker = 0; $worker < 8; $worker++) {
            $script = '$pdo = new PDO('.var_export($dsn, true).', '.var_export($config['username'], true).', '.var_export($config['password'], true).', [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);'.
                '$pdo->exec("set search_path to '.$schema.', public");'.
                '$pdo->exec("insert into users (id, name, email, password) select gen_random_uuid(), \'Concurrent test\', \'worker'.$worker.'-\' || n || \'@example.test\', \'not-an-authentication-secret\' from generate_series(1, 25) n");';
            $children[] = $process = new Process([PHP_BINARY, '-r', $script]);
            $process->setTimeout(30)->start();
        }
        foreach ($children as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());
        }
        $row = $pdo->query("select count(*) as total, count(distinct gamad_number) as unique_count, count(*) filter (where gamad_number = 'GMD-0000-0000-0000') as shared_candidate from $schema.users")->fetch(PDO::FETCH_ASSOC);
        expect((int) $row['total'])->toBe(200)->and((int) $row['unique_count'])->toBe(200)->and((int) $row['shared_candidate'])->toBe(1);
        expect((int) $pdo->query("select count(*) from $schema.gamad_number_reservations")->fetchColumn())->toBe(200);
    } finally {
        foreach ($children as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        $pdo->exec("drop schema $schema cascade");
    }
});
