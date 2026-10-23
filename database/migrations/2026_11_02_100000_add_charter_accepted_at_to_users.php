<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Charte de conduite (Lot 5a, D-146) : une fois la charte adoptée par le Président, l'inscription demande de l'accepter, et la date
 * d'acceptation est gardée. Vide pour les personnes inscrites avant l'adoption.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('charter_accepted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('charter_accepted_at');
        });
    }
};
