<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Conversations privées, remerciements et notifications (Lot 2b, D-097 et suivantes).
 *
 * Suppression d'un compte (D-097) : une conversation survit à la suppression de l'une des deux personnes, car l'autre doit
 * pouvoir la relire. Les références aux personnes passent donc à NULL (« Personne supprimée ») au lieu de supprimer la ligne ;
 * les messages de la personne supprimée sont effacés (corps vidé, « Message supprimé »).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            // Moment où un besoin est marqué résolu : sert à « À faire maintenant » (remercier dans la semaine).
            $table->timestamp('resolved_at')->nullable();
        });

        Schema::create('conversations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Un geste accepté ouvre une seule conversation. Le geste ou la publication peuvent disparaître : la conversation reste.
            $table->foreignUuid('gesture_id')->nullable()->unique()->constrained('gestures')->nullOnDelete();
            $table->foreignUuid('publication_id')->nullable()->constrained('publications')->nullOnDelete();
            // owner = l'auteur de la publication ; helper = la personne dont le geste a été accepté.
            $table->foreignUuid('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('helper_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('owner_read_at')->nullable();
            $table->timestamp('helper_read_at')->nullable();
            $table->timestamps();

            $table->index('owner_id');
            $table->index('helper_id');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->nullable()->constrained('users')->nullOnDelete();
            // NULL = message effacé avec le compte de son auteur (D-097).
            $table->string('body', 1000)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['conversation_id', 'created_at']);
            $table->index('sender_id');
        });

        Schema::create('gratitudes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // Un besoin porte au plus un remerciement (D-084). Il disparaît avec le besoin, ou avec la personne remerciée.
            $table->foreignUuid('publication_id')->unique()->constrained('publications')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('helper_id')->constrained('users')->cascadeOnDelete();
            $table->string('body', 280);
            // La personne remerciée peut masquer le remerciement de son profil.
            $table->timestamp('hidden_at')->nullable();
            $table->timestamps();

            $table->index(['helper_id', 'created_at']);
        });

        Schema::create('user_notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 24);
            $table->foreignUuid('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('publication_id')->nullable()->constrained('publications')->cascadeOnDelete();
            $table->foreignUuid('conversation_id')->nullable()->constrained('conversations')->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['user_id', 'read_at', 'created_at']);
        });

        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notifications');
        Schema::dropIfExists('gratitudes');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');

        Schema::table('publications', function (Blueprint $table) {
            $table->dropColumn('resolved_at');
        });
    }
};
