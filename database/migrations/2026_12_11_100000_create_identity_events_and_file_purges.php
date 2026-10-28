<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pas de clé étrangère sur user_id : la trace de sécurité survit à la fermeture du compte, comme le journal d'administration (D-030).
        // Aucune adresse IP ni navigateur n'y est conservé (D-048).
        Schema::create('identity_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->index();
            $table->string('type')->index();
            $table->jsonb('context')->nullable();
            $table->timestampTz('occurred_at')->useCurrent();
        });

        DB::unprepared(<<<'SQL'
            create or replace function identity_events_append_only() returns trigger as $$
            begin
                raise exception 'Les événements d''identité sont en ajout seul : modification et suppression interdites.';
            end;
            $$ language plpgsql
        SQL);

        DB::unprepared(<<<'SQL'
            create trigger identity_events_no_update_delete
            before update or delete on identity_events
            for each row execute function identity_events_append_only()
        SQL);

        // Fichiers à effacer après la validation d'une transaction. La ligne est écrite dans cette même transaction : si elle
        // est annulée, aucun fichier n'est perdu ; si le processus s'arrête après la validation, la ligne reste et sera reprise.
        Schema::create('file_purges', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('disk');
            $table->string('path');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('last_attempt_at')->nullable();

            $table->unique(['disk', 'path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('file_purges');
        DB::unprepared('drop trigger if exists identity_events_no_update_delete on identity_events');
        DB::unprepared('drop function if exists identity_events_append_only()');
        Schema::dropIfExists('identity_events');
    }
};
