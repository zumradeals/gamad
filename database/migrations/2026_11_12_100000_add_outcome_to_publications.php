<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', fn (Blueprint $table) => $table->string('outcome', 280)->nullable());
    }

    public function down(): void
    {
        Schema::table('publications', fn (Blueprint $table) => $table->dropColumn('outcome'));
    }
};
