<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-217 : pays africains sélectionnables. Colonnes ajoutées seulement, toutes facultatives : le code ISO d'un pays, la provenance
 * d'un pays ou d'une ville importés, et la localité écrite librement par une personne quand la sienne n'est pas dans la liste.
 * Aucune donnée existante n'est modifiée ; le pays déjà chargé (Côte d'Ivoire) reçoit seulement son code ISO.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('countries', function (Blueprint $table) {
            $table->string('iso2', 2)->nullable()->unique();
            $table->string('source', 80)->nullable();
        });

        Schema::table('cities', function (Blueprint $table) {
            $table->string('source', 80)->nullable();
        });

        Schema::table('profiles', function (Blueprint $table) {
            $table->string('locality', 100)->nullable();
        });

        // Le seul pays déjà chargé par le chargeur d'origine : on lui donne son code, sans toucher à son nom ni à ses villes.
        DB::table('countries')->where('normalized', 'cote d\'ivoire')->whereNull('iso2')->update(['iso2' => 'CI']);
    }

    public function down(): void
    {
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('locality'));
        Schema::table('cities', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('countries', function (Blueprint $table) {
            $table->dropUnique(['iso2']);
            $table->dropColumn(['iso2', 'source']);
        });
    }
};
