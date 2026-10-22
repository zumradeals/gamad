<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pas de clé étrangère sur actor_id : le journal survit à la suppression d'un compte (D-030).
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('actor_id')->nullable()->index();
            $table->string('actor_label');
            $table->string('action')->index();
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->text('reason')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['subject_type', 'subject_id']);
        });

        // Le journal est en ajout seul : la base refuse aussi les modifications qui contourneraient le modèle.
        DB::unprepared(<<<'SQL'
            create or replace function admin_actions_append_only() returns trigger as $$
            begin
                raise exception 'Le journal d''administration est en ajout seul : modification et suppression interdites.';
            end;
            $$ language plpgsql
        SQL);

        DB::unprepared(<<<'SQL'
            create trigger admin_actions_no_update_delete
            before update or delete on admin_actions
            for each row execute function admin_actions_append_only()
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists admin_actions_no_update_delete on admin_actions');
        DB::unprepared('drop function if exists admin_actions_append_only()');
        Schema::dropIfExists('admin_actions');
    }
};
