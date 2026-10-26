<?php

use App\Models\Opportunity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot D1 : les compétences recherchées d'une opportunité rejoignent le référentiel commun « skills » (capacités des profils et des
 * publications). Table additive ; les compétences déjà saisies en texte libre sont reprises sans rien supprimer (la colonne
 * `opportunities.skills` est conservée telle quelle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('opportunity_skill', function (Blueprint $table) {
            $table->uuid('opportunity_id');
            $table->uuid('skill_id');
            $table->unsignedSmallInteger('position')->default(0);

            $table->primary(['opportunity_id', 'skill_id']);
            $table->foreign('opportunity_id')->references('id')->on('opportunities')->cascadeOnDelete();
            $table->foreign('skill_id')->references('id')->on('skills')->restrictOnDelete();
            $table->index('skill_id');
        });

        Opportunity::linkLegacySkills();
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunity_skill');
    }
};
