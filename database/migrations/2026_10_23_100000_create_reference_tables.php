<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listes de référence du Lot 1 : pays, villes et domaines. Elles ne sont jamais supprimées, seulement désactivées
 * (une personne peut y être rattachée). `normalized` est la clé unique sans casse ni accents (D-066, D-068, D-069).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('normalized', 120)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('cities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('country_id')->constrained('countries')->restrictOnDelete();
            $table->string('name', 100);
            $table->string('area', 100)->nullable();
            $table->string('normalized', 220);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['country_id', 'normalized']);
        });

        Schema::create('domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);
            $table->string('normalized', 120)->unique();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('domains');
        Schema::dropIfExists('cities');
        Schema::dropIfExists('countries');
    }
};
