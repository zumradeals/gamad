<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vivre et reconnaître (Lot 3c, D-112, D-116, D-021) : publier au nom d'une ZUMRA, la Discussion de ses membres, et sa
 * reconnaissance par le super administrateur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            // Publication faite au nom d'une ZUMRA : author_id reste la personne (fondateur ou responsable) qui l'a écrite.
            $table->foreignUuid('zumra_id')->nullable()->constrained('zumras')->cascadeOnDelete();
            $table->index(['zumra_id', 'created_at']);
        });

        DB::statement('alter table publications drop constraint publications_type_check');
        DB::statement("alter table publications add constraint publications_type_check check (type in ('need', 'knowledge', 'learning_wish', 'offer', 'zumra_call'))");
        DB::statement('alter table publications drop constraint publications_visibility_check');
        DB::statement("alter table publications add constraint publications_visibility_check check (visibility in ('public', 'members', 'zumra'))");
        // Un Appel à rejoindre et le niveau « ZUMRA seulement » n'existent que pour une publication de ZUMRA.
        DB::statement("alter table publications add constraint publications_zumra_only_for_zumras check ((type <> 'zumra_call' or zumra_id is not null) and (visibility <> 'zumra' or zumra_id is not null))");

        Schema::table('zumras', function (Blueprint $table) {
            $table->timestamp('recognized_at')->nullable();
            $table->foreignUuid('recognized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('recognized_at');
        });

        Schema::create('zumra_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zumra_id')->constrained('zumras')->cascadeOnDelete();
            // Les messages d'une personne disparaissent avec son compte.
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('body', 1000);
            $table->timestamp('created_at', 6)->useCurrent();

            $table->index(['zumra_id', 'created_at']);
        });

        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred', 'zumra_recognized', 'zumra_recognition_withdrawn'))");
    }

    public function down(): void
    {
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred'))");

        Schema::dropIfExists('zumra_posts');

        Schema::table('zumras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recognized_by');
            $table->dropColumn('recognized_at');
        });

        DB::statement('alter table publications drop constraint publications_zumra_only_for_zumras');
        DB::statement('alter table publications drop constraint publications_visibility_check');
        DB::statement("alter table publications add constraint publications_visibility_check check (visibility in ('public', 'members'))");
        DB::statement('alter table publications drop constraint publications_type_check');
        DB::statement("alter table publications add constraint publications_type_check check (type in ('need', 'knowledge', 'learning_wish', 'offer'))");

        Schema::table('publications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zumra_id');
        });
    }
};
