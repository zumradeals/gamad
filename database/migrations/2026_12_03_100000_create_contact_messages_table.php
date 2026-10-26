<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pages Aide et Contact (D-208), additif : les messages du formulaire de contact public, lus par le seul super administrateur.
 * Aucune pièce jointe. Le compte est facultatif et ne bloque jamais la suppression d'un compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 80);
            $table->string('email', 254);
            $table->string('subject', 20);
            $table->text('body');
            // new, read, handled.
            $table->string('state', 8)->default('new');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('handled_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['state', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
