<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ZUMRA et leurs membres (Lot 3a, D-110 à D-114). Une ZUMRA a un seul domaine, qui ne change pas ; son nom est unique sans
 * tenir compte de la casse, des accents ni des espaces. Une personne y a un rôle : fondateur (un seul), responsable ou membre.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('zumras', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 80);
            // Clé d'unicité du nom (casse, accents et espaces ignorés) et clé de recherche du nom et de la mission.
            $table->string('name_key', 160)->unique();
            $table->string('search', 700);
            $table->foreignUuid('domain_id')->constrained('domains')->restrictOnDelete();
            $table->foreignUuid('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->string('format', 8);
            $table->string('mission', 500);
            $table->timestamps();

            $table->index('domain_id');
            $table->index('city_id');
        });

        DB::statement("alter table zumras add constraint zumras_format_check check (format in ('online', 'onsite', 'hybrid'))");

        Schema::create('zumra_memberships', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zumra_id')->constrained('zumras')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 8);
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            // Une personne n'a qu'un rôle par ZUMRA.
            $table->unique(['zumra_id', 'user_id']);
            $table->index('user_id');
        });

        DB::statement("alter table zumra_memberships add constraint zumra_memberships_role_check check (role in ('founder', 'manager', 'member'))");
        // Un seul fondateur par ZUMRA (D-111) : la base le garantit.
        DB::statement("create unique index zumra_memberships_one_founder on zumra_memberships (zumra_id) where role = 'founder'");
    }

    public function down(): void
    {
        Schema::dropIfExists('zumra_memberships');
        Schema::dropIfExists('zumras');
    }
};
