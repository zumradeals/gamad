<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const KINDS_BEFORE = "'gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred', 'zumra_recognized', 'zumra_recognition_withdrawn', 'project_participation_requested', 'project_participation_accepted', 'project_member_added', 'project_member_removed'";

    private const KINDS_ADDED = "'publication_explained', 'moderation_notice_issued', 'moderation_notice_explained', 'moderation_notice_closed', 'person_reinstated'";

    public function up(): void
    {
        // Suspension d'une personne (D-145) : un état sur le compte, avec le motif que la personne verra.
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable();
            $table->foreignUuid('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('suspension_reason')->nullable();
        });

        DB::statement('alter table users add constraint users_suspension_check check ((suspended_at is null) = (suspension_reason is null))');

        // Avis de la modération : le droit de s'expliquer qui précède toute suspension (PRODUIT §6, règle 6).
        Schema::create('moderation_notices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('reason');
            $table->text('explanation')->nullable();
            $table->timestamp('explained_at')->nullable();
            $table->string('resolution', 12)->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        DB::statement("alter table moderation_notices add constraint moderation_notices_resolution_check check ((resolution is null) = (resolved_at is null) and (resolution is null or resolution in ('suspended', 'closed')))");
        // Un seul avis ouvert par personne.
        DB::statement('create unique index moderation_notices_one_open on moderation_notices (user_id) where resolved_at is null');

        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement('alter table user_notifications add constraint user_notifications_kind_check check (kind in ('.self::KINDS_BEFORE.', '.self::KINDS_ADDED.'))');
    }

    public function down(): void
    {
        DB::statement('delete from user_notifications where kind in ('.self::KINDS_ADDED.')');
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement('alter table user_notifications add constraint user_notifications_kind_check check (kind in ('.self::KINDS_BEFORE.'))');

        Schema::dropIfExists('moderation_notices');

        DB::statement('alter table users drop constraint users_suspension_check');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('suspended_by');
            $table->dropColumn(['suspended_at', 'suspension_reason']);
        });
    }
};
