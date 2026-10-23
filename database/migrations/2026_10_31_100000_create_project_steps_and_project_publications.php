<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Avancer (Lot 4b, D-134 à D-137) : les étapes d'un projet, et les publications faites au nom d'un projet (avancée de projet,
 * réalisation). Une publication de projet est toujours une publication de la ZUMRA du projet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_steps', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title', 80);
            $table->string('state', 12)->default('todo');
            $table->unsignedInteger('position');
            $table->timestamp('state_changed_at')->useCurrent();
            $table->timestamps();

            // Une position par projet : l'ordre des étapes est sans équivoque.
            $table->unique(['project_id', 'position']);
        });

        DB::statement("alter table project_steps add constraint project_steps_state_check check (state in ('todo', 'in_progress', 'done'))");

        Schema::table('publications', function (Blueprint $table) {
            // Publication faite au nom d'un projet ; zumra_id est alors celui de la ZUMRA du projet.
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->index(['project_id', 'created_at']);
        });

        DB::statement('alter table publications drop constraint publications_type_check');
        DB::statement("alter table publications add constraint publications_type_check check (type in ('need', 'knowledge', 'learning_wish', 'offer', 'zumra_call', 'project_update', 'achievement'))");
        // Une avancée ou une réalisation n'existe que pour un projet (donc pour une ZUMRA), et seules elles ont un projet.
        DB::statement("alter table publications add constraint publications_project_types check ((type in ('project_update', 'achievement')) = (project_id is not null) and (project_id is null or zumra_id is not null))");
    }

    public function down(): void
    {
        DB::statement('alter table publications drop constraint publications_project_types');
        DB::statement('alter table publications drop constraint publications_type_check');
        DB::statement("alter table publications add constraint publications_type_check check (type in ('need', 'knowledge', 'learning_wish', 'offer', 'zumra_call'))");

        Schema::table('publications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        Schema::dropIfExists('project_steps');
    }
};
