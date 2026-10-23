<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Entrer dans une ZUMRA (Lot 3b, D-110) : demandes d'adhésion et invitations, et les notifications qui les accompagnent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('join_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zumra_id')->constrained('zumras')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('message', 280)->nullable();
            $table->string('state', 12)->default('pending');
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // Une demande par personne et par ZUMRA, réutilisée si la personne redemande après avoir retiré ou quitté.
            $table->unique(['zumra_id', 'user_id']);
            $table->index(['zumra_id', 'state']);
        });

        DB::statement("alter table join_requests add constraint join_requests_state_check check (state in ('pending', 'accepted', 'declined', 'withdrawn'))");

        Schema::create('invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zumra_id')->constrained('zumras')->cascadeOnDelete();
            // La personne invitée, et celle qui l'a invitée (son compte supprimé, l'invitation reste, sans nom).
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('message', 280)->nullable();
            $table->string('state', 12)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'state']);
            $table->index(['zumra_id', 'state']);
        });

        DB::statement("alter table invitations add constraint invitations_state_check check (state in ('pending', 'accepted', 'refused', 'cancelled', 'expired'))");
        // Une seule invitation en attente par personne et par ZUMRA.
        DB::statement("create unique index invitations_one_pending on invitations (zumra_id, user_id) where state = 'pending'");

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->foreignUuid('zumra_id')->nullable()->constrained('zumras')->cascadeOnDelete();
        });

        // Les noms des nouvelles sortes dépassent 24 caractères.
        DB::statement('alter table user_notifications alter column kind type varchar(40)');
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored', 'zumra_request_received', 'zumra_request_accepted', 'zumra_invitation_received', 'zumra_invitation_accepted', 'zumra_role_granted', 'zumra_member_removed', 'zumra_founder_transferred'))");
    }

    public function down(): void
    {
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ('gesture_received', 'gesture_accepted', 'message_received', 'gratitude_received', 'publication_hidden', 'publication_restored'))");

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('zumra_id');
        });

        Schema::dropIfExists('invitations');
        Schema::dropIfExists('join_requests');
    }
};
