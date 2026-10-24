<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('identity_verifications', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('user_id')->unique()->constrained()->cascadeOnDelete();
            $t->string('state', 20);
            $t->unsignedInteger('revision')->default(1);
            $t->string('checked_name');
            $t->string('method', 20)->nullable();
            $t->foreignUuid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->text('reason')->nullable();
            $t->text('instruction')->nullable();
            $t->timestamps();
            $t->index(['state', 'created_at']);
        });
        Schema::create('work_attestations', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('publication_id')->constrained()->cascadeOnDelete();
            $t->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $t->foreignUuid('attester_id')->constrained('users')->cascadeOnDelete();
            $t->text('claim');
            $t->date('performed_on');
            $t->string('role', 20)->nullable();
            $t->string('state', 20);
            $t->unsignedInteger('revision')->default(1);
            $t->unsignedInteger('identity_revision')->nullable();
            $t->timestamp('confirmed_at')->nullable();
            $t->text('reason')->nullable();
            $t->timestamps();
            $t->unique(['publication_id', 'attester_id']);
            $t->index(['attester_id', 'state']);
            $t->index(['author_id', 'state']);
        });
        Schema::create('trust_reports', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->foreignUuid('reporter_id')->constrained('users')->cascadeOnDelete();
            $t->foreignUuid('subject_id')->constrained('users')->cascadeOnDelete();
            $t->foreignUuid('work_attestation_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('target_key', 50);
            $t->string('category', 20);
            $t->text('details');
            $t->string('state', 20)->default('open');
            $t->unsignedInteger('revision')->default(1);
            $t->text('response')->nullable();
            $t->foreignUuid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('closed_at')->nullable();
            $t->timestamps();
            $t->index(['state', 'created_at']);
        });
        DB::statement("alter table identity_verifications add constraint identity_state check (state in ('pending','verified','declined','revoked','withdrawn'))");
        DB::statement("alter table identity_verifications add constraint identity_verified check (state <> 'verified' or (verified_at is not null and expires_at is not null and expires_at > verified_at and method is not null and method in ('in_person','live_review')))");
        DB::statement("alter table work_attestations add constraint work_state check (state in ('pending','confirmed','declined','withdrawn','revoked'))");
        DB::statement('alter table work_attestations add constraint work_distinct check (author_id <> attester_id)');
        DB::statement("alter table work_attestations add constraint work_confirmed check (state <> 'confirmed' or (confirmed_at is not null and identity_revision is not null and role is not null and role in ('client','colleague','witness')))");
        DB::statement("alter table trust_reports add constraint trust_report_state check (state in ('open','closed'))");
        DB::statement("alter table trust_reports add constraint trust_report_closed check ((state = 'closed') = (closed_at is not null))");
        DB::statement("create unique index trust_reports_one_open on trust_reports (reporter_id,target_key) where state = 'open'");
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('connection_requested', 'connection_accepted', 'gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred', 'zumra_recognized', 'zumra_recognition_withdrawn', 'project_participation_requested', 'project_participation_accepted', 'project_member_added', 'project_member_removed', 'publication_explained', 'moderation_notice_issued', 'moderation_notice_explained', 'moderation_notice_closed', 'person_reinstated', 'match_found', 'trust_updated', 'trust_review'))");
    }

    public function down(): void
    {
        DB::table('user_notifications')->whereIn('kind', ['trust_updated', 'trust_review'])->delete();
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('connection_requested', 'connection_accepted', 'gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred', 'zumra_recognized', 'zumra_recognition_withdrawn', 'project_participation_requested', 'project_participation_accepted', 'project_member_added', 'project_member_removed', 'publication_explained', 'moderation_notice_issued', 'moderation_notice_explained', 'moderation_notice_closed', 'person_reinstated', 'match_found'))");
        Schema::dropIfExists('trust_reports');
        Schema::dropIfExists('work_attestations');
        Schema::dropIfExists('identity_verifications');
    }
};
