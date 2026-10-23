<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Signalements et modération minimale (Lot 2c, D-089). Une publication masquée garde son texte (pour que l'auteur puisse
 * s'expliquer et la modération la rétablir) ; elle n'apparaît plus à personne d'autre que son auteur et la modération.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable();
            $table->foreignUuid('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            // Le motif donné par la modération : l'auteur le voit.
            $table->string('hidden_reason', 500)->nullable();
            // L'explication de l'auteur, lue par la modération avant de rétablir ou de maintenir.
            $table->string('author_explanation', 500)->nullable();
            $table->timestamp('explained_at')->nullable();

            $table->index('hidden_at');
        });

        // Masquée = motif et date toujours ensemble.
        DB::statement('alter table publications add constraint publications_hidden_consistent check ((hidden_at is null) = (hidden_reason is null))');

        Schema::create('reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('publication_id')->constrained('publications')->cascadeOnDelete();
            // La personne qui signale n'est jamais montrée à l'auteur. Son compte supprimé, le signalement reste (sans nom).
            $table->foreignUuid('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 24);
            $table->string('details', 280)->nullable();
            // Vide = à examiner. « hidden » : la publication a été masquée ; « dismissed » : classé sans suite.
            $table->string('resolution', 16)->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            // Une personne ne signale qu'une fois une même publication.
            $table->unique(['publication_id', 'reporter_id']);
            $table->index(['resolution', 'created_at']);
        });

        DB::statement("alter table reports add constraint reports_category_check check (category in ('offensive', 'scam', 'misinformation', 'spam', 'privacy', 'other'))");
        DB::statement("alter table reports add constraint reports_resolution_check check (resolution is null or resolution in ('hidden', 'dismissed'))");

        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored'))");
    }

    public function down(): void
    {
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received'))");

        Schema::dropIfExists('reports');

        Schema::table('publications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hidden_by');
            $table->dropColumn(['hidden_at', 'hidden_reason', 'author_explanation', 'explained_at']);
        });
    }
};
