<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retrait d'une piste devenue interdite ou obsolète : la ligne reste (mesure, pas de nouvelle alerte), elle n'est plus exposée.
        Schema::table('match_suggestions', function (Blueprint $table) {
            $table->timestampTz('withdrawn_at')->nullable();
            $table->string('withdrawn_reason', 24)->nullable();
            $table->index(['user_id', 'withdrawn_at']);
        });
        DB::statement('alter table match_suggestions add constraint match_suggestions_withdrawn_check check ((withdrawn_at is null) = (withdrawn_reason is null))');

        // Une recherche de correspondances : l'état réel, visible par la personne concernée et mesurable. Aucune donnée personnelle.
        Schema::create('matching_searches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('publication_id')->nullable()->constrained('publications')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('state', 10)->default('queued');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedInteger('produced')->default(0);
            $table->jsonb('cursor')->nullable();
            $table->boolean('rerun')->default(false);
            $table->string('error_code', 120)->nullable();
            $table->string('cancel_reason', 24)->nullable();
            $table->timestampTz('requested_at')->useCurrent();
            $table->timestampTz('started_at')->nullable();
            $table->timestampTz('finished_at')->nullable();
            $table->timestampTz('heartbeat_at')->nullable();
            $table->index(['state', 'requested_at']);
            $table->index('publication_id');
            $table->index('user_id');
        });
        DB::statement("alter table matching_searches add constraint matching_searches_state_check check (state in ('queued', 'running', 'done', 'failed', 'cancelled'))");
        DB::statement('alter table matching_searches add constraint matching_searches_subject_check check ((publication_id is null) <> (user_id is null))');
        // Une seule recherche ouverte par sujet : les demandes en double se regroupent (idempotence garantie par la base).
        DB::statement("create unique index matching_searches_open_publication on matching_searches (publication_id) where state in ('queued', 'running')");
        DB::statement("create unique index matching_searches_open_user on matching_searches (user_id) where state in ('queued', 'running')");

        // Journal des exécutions de tâches (une ligne par tentative) : durées, reprises, résultats chiffrés. Ni sujet, ni contenu.
        Schema::create('execution_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('job', 80)->index();
            $table->unsignedSmallInteger('attempt');
            $table->string('outcome', 10);
            $table->unsignedInteger('duration_ms');
            $table->jsonb('metrics')->nullable();
            $table->string('error_code', 120)->nullable();
            $table->timestampTz('ran_at')->useCurrent()->index();
        });
        DB::statement("alter table execution_runs add constraint execution_runs_outcome_check check (outcome in ('success', 'failure'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_runs');
        DB::statement('drop index if exists matching_searches_open_user');
        DB::statement('drop index if exists matching_searches_open_publication');
        Schema::dropIfExists('matching_searches');
        DB::statement('alter table match_suggestions drop constraint if exists match_suggestions_withdrawn_check');
        Schema::table('match_suggestions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'withdrawn_at']);
            $table->dropColumn(['withdrawn_at', 'withdrawn_reason']);
        });
    }
};
