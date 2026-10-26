<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lot D3 : plafond quotidien de pages consultées par une entreprise dans la découverte de personnes. Une ligne par entreprise et par
 * jour, incrémentée par une seule instruction SQL atomique (jamais « lire puis écrire »), donc sûre en cas de requêtes simultanées.
 * Aucune donnée sur les personnes consultées n'est conservée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_discovery_views', function (Blueprint $table) {
            $table->foreignUuid('company_id')->constrained()->cascadeOnDelete();
            $table->date('day');
            $table->unsignedSmallInteger('views')->default(0);

            $table->primary(['company_id', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_discovery_views');
    }
};
