<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', fn (Blueprint $table) => $table->jsonb('matching_preferences')->nullable());
        Schema::table('publications', fn (Blueprint $table) => $table->jsonb('matching')->nullable());
        Schema::create('match_suggestions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('publication_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('profile_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('offer_id')->nullable()->constrained('publications')->cascadeOnDelete();
            $table->string('signature', 64);
            $table->timestamp('ignored_at')->nullable();
            $table->timestamp('alerted_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'signature']);
            $table->index('publication_id');
            $table->index('profile_id');
            $table->index('offer_id');
        });
        DB::statement('alter table match_suggestions add constraint match_suggestions_target check ((profile_id is null) <> (offer_id is null))');
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('connection_requested', 'connection_accepted', 'gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred', 'zumra_recognized', 'zumra_recognition_withdrawn', 'project_participation_requested', 'project_participation_accepted', 'project_member_added', 'project_member_removed', 'publication_explained', 'moderation_notice_issued', 'moderation_notice_explained', 'moderation_notice_closed', 'person_reinstated', 'match_found'))");
    }

    public function down(): void
    {
        DB::table('user_notifications')->where('kind', 'match_found')->delete();
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('connection_requested', 'connection_accepted', 'gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred', 'zumra_recognized', 'zumra_recognition_withdrawn', 'project_participation_requested', 'project_participation_accepted', 'project_member_added', 'project_member_removed', 'publication_explained', 'moderation_notice_issued', 'moderation_notice_explained', 'moderation_notice_closed', 'person_reinstated'))");
        Schema::dropIfExists('match_suggestions');
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('matching_preferences'));
        Schema::table('publications', fn (Blueprint $table) => $table->dropColumn('matching'));
    }
};
