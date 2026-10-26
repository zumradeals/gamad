<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Z1 (D-205), additif : la nature d'une ZUMRA (sociale, professionnelle ou religieuse) et le poste de gouvernance d'un responsable.
 * Un poste proposé doit être accepté par la personne (« pending ») avant d'être effectif. Les colonnes sont facultatives : aucune ZUMRA existante n'est modifiée, aucune donnée n'est supprimée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zumras', function (Blueprint $table) {
            $table->string('sphere', 20)->nullable()->after('format');
        });

        Schema::table('zumra_memberships', function (Blueprint $table) {
            $table->string('duty', 30)->nullable()->after('role');
            // « pending » : poste proposé, pas encore accepté. Vide avec un poste : poste déjà effectif (responsabilités d'avant l'acceptation).
            $table->string('duty_state', 10)->nullable()->after('duty');
        });

        // Un poste n'a qu'une personne par ZUMRA.
        DB::statement('create unique index zumra_memberships_duty_unique on zumra_memberships (zumra_id, duty) where duty is not null');
    }

    public function down(): void
    {
        DB::statement('drop index if exists zumra_memberships_duty_unique');

        Schema::table('zumra_memberships', function (Blueprint $table) {
            $table->dropColumn(['duty', 'duty_state']);
        });

        Schema::table('zumras', function (Blueprint $table) {
            $table->dropColumn('sphere');
        });
    }
};
