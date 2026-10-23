<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projets (Lot 4a, D-005, D-126 à D-129). Un projet appartient toujours à une ZUMRA ; son nom est unique dans la ZUMRA (casse,
 * accents et espaces ignorés). Chaque changement d'état est gardé avec la personne qui l'a fait.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zumra_id')->constrained('zumras')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('name_key', 160);
            $table->string('search', 700);
            $table->string('goal', 500);
            $table->string('state', 12)->default('idea');
            $table->timestamp('state_changed_at')->useCurrent();
            $table->timestamps();

            $table->unique(['zumra_id', 'name_key']);
            $table->index('state');
        });

        DB::statement("alter table projects add constraint projects_state_check check (state in ('idea', 'in_progress', 'done', 'abandoned'))");

        Schema::create('project_state_changes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            // Vide à la création du projet ; une personne dont le compte disparaît laisse l'entrée, sans son nom.
            $table->string('from_state', 12)->nullable();
            $table->string('to_state', 12);
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at', 6)->useCurrent();

            $table->index(['project_id', 'created_at']);
        });

        DB::statement("alter table project_state_changes add constraint project_state_changes_to_check check (to_state in ('idea', 'in_progress', 'done', 'abandoned'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('project_state_changes');
        Schema::dropIfExists('projects');
    }
};
