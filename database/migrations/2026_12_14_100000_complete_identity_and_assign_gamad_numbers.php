<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL runs this migration in one transaction: no account can slip between backfill and protection.
        DB::statement("set local lock_timeout = '5s'");
        Schema::create('gamad_number_reservations', function (Blueprint $table) {
            // No owner, email, UUID or timestamp: only the minimum needed to prevent reassignment after closure.
            $table->string('number', 18)->primary();
        });
        DB::unprepared(<<<'SQL'
            create function gamad_number_candidate() returns text as $$
                select 'GMD-' || upper(substr(value, 1, 4) || '-' || substr(value, 5, 4) || '-' || substr(value, 9, 4))
                from (select replace(gen_random_uuid()::text, '-', '') as value) random_value;
            $$ language sql volatile;

            create function gamad_allocate_number() returns text as $$
            declare candidate text; reserved text;
            begin
                for attempt in 1..128 loop
                    candidate := gamad_number_candidate();
                    insert into gamad_number_reservations (number) values (candidate)
                        on conflict do nothing returning number into reserved;
                    if reserved is not null then return reserved; end if;
                end loop;
                raise exception 'Impossible de réserver un numéro GAMAD unique ; création annulée.';
            end;
            $$ language plpgsql;

            create function gamad_reservations_immutable() returns trigger as $$
            begin
                raise exception 'Un numéro GAMAD réservé ne peut être modifié, effacé ni réattribué.';
            end;
            $$ language plpgsql;
            create trigger gamad_reservations_no_change before update or delete on gamad_number_reservations
                for each row execute function gamad_reservations_immutable();
            create trigger gamad_reservations_no_truncate before truncate on gamad_number_reservations
                for each statement execute function gamad_reservations_immutable();
        SQL);
        Schema::table('users', fn (Blueprint $table) => $table->string('gamad_number', 18)->nullable());
        DB::statement('update users set gamad_number = gamad_allocate_number() where gamad_number is null');
        DB::statement('alter table users alter column gamad_number set not null');
        DB::statement('alter table users add constraint users_gamad_number_unique unique (gamad_number)');
        DB::statement('alter table users add constraint users_gamad_number_reserved foreign key (gamad_number) references gamad_number_reservations (number)');
        DB::statement("alter table gamad_number_reservations add constraint gamad_number_format check (number ~ '^GMD-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}$')");
        DB::unprepared(<<<'SQL'
            create function users_protect_gamad_number() returns trigger as $$
            begin
                if TG_OP = 'INSERT' then
                    if NEW.gamad_number is not null then
                        raise exception 'Le numéro GAMAD est attribué automatiquement, jamais choisi.';
                    end if;
                    NEW.gamad_number := gamad_allocate_number();
                elsif NEW.gamad_number is distinct from OLD.gamad_number then
                    raise exception 'Le numéro GAMAD du compte est immuable.';
                end if;
                return NEW;
            end;
            $$ language plpgsql;
            create trigger users_gamad_number_guard before insert or update on users
                for each row execute function users_protect_gamad_number();
        SQL);
        Schema::table('identity_events', function (Blueprint $table) {
            $table->index('occurred_at');
            $table->index(['type', 'occurred_at']);
        });
    }

    public function down(): void
    {
        // Dropping an issued namespace would permit reuse on a later migration. Refuse rather than silently violate stability.
        if (DB::table('gamad_number_reservations')->exists()) {
            throw new LogicException('Des numéros GAMAD ont été attribués : retour destructif interdit. Corriger par migration additive.');
        }
        Schema::table('identity_events', function (Blueprint $table) {
            $table->dropIndex(['type', 'occurred_at']);
            $table->dropIndex(['occurred_at']);
        });
        DB::unprepared('drop trigger users_gamad_number_guard on users; drop function users_protect_gamad_number()');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign('users_gamad_number_reserved');
            $table->dropUnique('users_gamad_number_unique');
            $table->dropColumn('gamad_number');
        });
        Schema::drop('gamad_number_reservations');
        DB::unprepared('drop function gamad_reservations_immutable(); drop function gamad_allocate_number(); drop function gamad_number_candidate()');
    }
};
