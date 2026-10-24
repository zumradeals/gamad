<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('connection_requested', 'connection_accepted', 'gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred', 'zumra_recognized', 'zumra_recognition_withdrawn', 'project_participation_requested', 'project_participation_accepted', 'project_member_added', 'project_member_removed', 'publication_explained', 'moderation_notice_issued', 'moderation_notice_explained', 'moderation_notice_closed', 'person_reinstated'))");
        Schema::create('person_follows', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('followed_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['user_id', 'followed_id']);
            $table->index('followed_id');
        });
        Schema::create('person_blocks', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('blocked_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['user_id', 'blocked_id']);
            $table->index('blocked_id');
        });
        Schema::create('person_connections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('first_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('second_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('sender_id')->constrained('users')->cascadeOnDelete();
            $table->string('state', 12);
            $table->unsignedInteger('revision')->default(1);
            $table->string('note', 280)->nullable();
            $table->foreignUuid('conversation_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->unique(['first_id', 'second_id']);
            $table->index('second_id');
            $table->index('sender_id');
            $table->index('conversation_id');
        });
        DB::statement('alter table person_connections add constraint person_connections_pair check (first_id < second_id and sender_id in (first_id, second_id))');
        DB::statement("alter table person_connections add constraint person_connections_state check (state in ('pending', 'accepted', 'declined', 'withdrawn', 'removed'))");
        DB::statement('alter table person_follows add constraint person_follows_self check (user_id <> followed_id)');
        DB::statement('alter table person_blocks add constraint person_blocks_self check (user_id <> blocked_id)');
        Schema::table('conversations', fn (Blueprint $table) => $table->string('origin', 12)->default('publication'));
        DB::statement("alter table conversations add constraint conversations_origin_check check (origin in ('publication', 'relation'))");
        Schema::table('profiles', function (Blueprint $table) {
            $table->text('introduction')->nullable();
            $table->jsonb('experiences')->nullable();
            $table->jsonb('skill_details')->nullable();
            $table->jsonb('featured_publications')->nullable();
        });
        Schema::table('publications', function (Blueprint $table) {
            $table->string('media_path')->nullable();
            $table->string('media_kind', 8)->nullable();
            $table->string('media_mime', 64)->nullable();
            $table->string('media_name')->nullable();
        });
        DB::statement('alter table publications drop constraint publications_type_check');
        DB::statement("alter table publications add constraint publications_type_check check (type in ('need', 'knowledge', 'learning_wish', 'offer', 'zumra_call', 'project_update', 'achievement', 'personal_work', 'share'))");
        DB::statement("alter table publications add constraint publications_personal_types check (type not in ('personal_work', 'share') or (zumra_id is null and project_id is null))");
    }

    public function down(): void
    {
        // Les nouveaux contenus ne sont jamais supprimés silencieusement par un retour de version.
        if (DB::table('publications')->whereIn('type', ['personal_work', 'share'])->exists()) {
            throw new RuntimeException('Des publications personnelles existent : exportez-les avant le retour de version.');
        }
        DB::statement('alter table publications drop constraint publications_personal_types');
        DB::statement('alter table publications drop constraint publications_type_check');
        DB::statement("alter table publications add constraint publications_type_check check (type in ('need', 'knowledge', 'learning_wish', 'offer', 'zumra_call', 'project_update', 'achievement'))");
        Schema::table('publications', fn (Blueprint $table) => $table->dropColumn(['media_path', 'media_kind', 'media_mime', 'media_name']));
        Schema::table('conversations', fn (Blueprint $table) => $table->dropColumn('origin'));
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn(['introduction', 'experiences', 'skill_details', 'featured_publications']));
        DB::table('user_notifications')->whereIn('kind', ['connection_requested', 'connection_accepted'])->delete();
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred', 'zumra_recognized', 'zumra_recognition_withdrawn', 'project_participation_requested', 'project_participation_accepted', 'project_member_added', 'project_member_removed', 'publication_explained', 'moderation_notice_issued', 'moderation_notice_explained', 'moderation_notice_closed', 'person_reinstated'))");
        Schema::dropIfExists('person_connections');
        Schema::dropIfExists('person_blocks');
        Schema::dropIfExists('person_follows');
    }
};
