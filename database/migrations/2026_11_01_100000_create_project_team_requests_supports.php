<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Équipe, besoins et soutiens (Lot 4c, D-138 à D-142) : l'équipe d'un projet, les demandes « Je veux participer », les soutiens
 * donnés à une avancée de projet, les besoins publiés au nom d'un projet et les notifications qui s'y rapportent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_members', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // La personne qui a ajouté ; vide si elle est entrée sur demande, ou si le compte de celui qui l'a ajoutée disparaît.
            $table->foreignUuid('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('joined_at')->useCurrent();
            $table->timestamps();

            // Une personne n'est qu'une fois dans l'équipe d'un projet.
            $table->unique(['project_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('participation_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('message', 280)->nullable();
            $table->string('state', 12)->default('pending');
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // Une demande par personne et par projet ; elle est reprise si la personne redemande après l'avoir retirée.
            $table->unique(['project_id', 'user_id']);
            $table->index(['project_id', 'state']);
        });

        DB::statement("alter table participation_requests add constraint participation_requests_state_check check (state in ('pending', 'accepted', 'declined', 'withdrawn'))");

        Schema::create('publication_supports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Un seul soutien par personne et par publication.
            $table->unique(['publication_id', 'user_id']);
            $table->index('user_id');
        });

        // Un besoin peut maintenant être publié au nom d'un projet ; les autres types, sauf l'avancée et la réalisation, non.
        DB::statement('alter table publications drop constraint publications_project_types');
        DB::statement("alter table publications add constraint publications_project_types check ((type not in ('project_update', 'achievement') or project_id is not null) and (project_id is null or (zumra_id is not null and type in ('project_update', 'achievement', 'need'))))");

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
        });

        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred', 'zumra_recognized', 'zumra_recognition_withdrawn', 'project_participation_requested', 'project_participation_accepted', 'project_member_added', 'project_member_removed'))");
    }

    public function down(): void
    {
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred', 'zumra_recognized', 'zumra_recognition_withdrawn'))");

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('project_id');
        });

        DB::statement('alter table publications drop constraint publications_project_types');
        DB::statement("alter table publications add constraint publications_project_types check ((type in ('project_update', 'achievement')) = (project_id is not null) and (project_id is null or zumra_id is not null))");

        Schema::dropIfExists('publication_supports');
        Schema::dropIfExists('participation_requests');
        Schema::dropIfExists('project_members');
    }
};
