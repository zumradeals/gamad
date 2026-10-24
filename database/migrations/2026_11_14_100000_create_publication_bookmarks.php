<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D-193 : enregistrements strictement personnels, sans compteur ni approbation. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_bookmarks', function (Blueprint $table) {
            $table->uuid('user_id');
            $table->uuid('publication_id');
            $table->timestamp('created_at')->useCurrent();

            $table->primary(['user_id', 'publication_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('publication_id')->references('id')->on('publications')->cascadeOnDelete();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_bookmarks');
    }
};
