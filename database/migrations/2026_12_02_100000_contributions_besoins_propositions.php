<?php

use App\Enums\NotificationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot C1 — Contributions : besoins et entraide volontaire (D-207). Les besoins restent des publications « J'ai besoin » (ils gagnent
 * un titre, un type d'aide, une échéance et, pour GAMAD, un marquage officiel) ; les propositions de contribution, spontanées ou
 * en réponse à un besoin, ont leur propre table. Aucune somme n'est enregistrée : une intention financière n'est ni payée ni encaissée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->string('title', 80)->nullable();
            $table->string('help_kind', 12)->nullable();
            $table->date('needed_by')->nullable();
            $table->boolean('official')->default(false);
        });
        DB::statement("alter table publications add constraint publications_help_kind_check check (help_kind is null or help_kind in ('financial', 'skills', 'time', 'material'))");
        DB::statement('alter table publications add constraint publications_official_check check (not official or (zumra_id is null and project_id is null and company_id is null))');

        Schema::table('project_members', function (Blueprint $table) {
            // Habilitation donnée par le porteur : publier un besoin et traiter les propositions au nom du projet.
            $table->boolean('manages_contributions')->default(false);
        });

        Schema::table('profiles', function (Blueprint $table) {
            // La personne accepte de recevoir des propositions d'aide spontanées sur son profil.
            $table->boolean('accepts_contributions')->default(false);
        });

        Schema::create('contribution_proposals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('need_id')->nullable()->constrained('publications')->nullOnDelete();
            $table->string('kind', 12);
            // Un seul bénéficiaire : une ZUMRA, un projet, une entreprise, une personne, ou GAMAD (official).
            $table->foreignUuid('zumra_id')->nullable()->constrained('zumras')->cascadeOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('person_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->boolean('official')->default(false);
            $table->string('offer', 500);
            $table->string('message', 500)->nullable();
            $table->string('visibility', 8)->default('private');
            $table->string('state', 10)->default('proposed');
            $table->foreignUuid('domain_id')->nullable()->constrained('domains')->nullOnDelete();
            $table->foreignUuid('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->string('decision_note', 280)->nullable();
            $table->timestamp('declared_at')->nullable();
            $table->timestamp('realized_at')->nullable();
            $table->foreignUuid('realized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['state', 'visibility']);
            $table->index('user_id');
            $table->index('need_id');
        });
        DB::statement("alter table contribution_proposals add constraint contribution_proposals_kind_check check (kind in ('financial', 'skills', 'time', 'material'))");
        DB::statement("alter table contribution_proposals add constraint contribution_proposals_visibility_check check (visibility in ('private', 'public'))");
        DB::statement("alter table contribution_proposals add constraint contribution_proposals_state_check check (state in ('proposed', 'accepted', 'declined', 'withdrawn', 'declared', 'realized'))");
        DB::statement('alter table contribution_proposals add constraint contribution_proposals_one_beneficiary_check check ((zumra_id is not null)::int + (project_id is not null)::int + (company_id is not null)::int + (person_id is not null)::int + official::int = 1)');

        Schema::table('conversations', function (Blueprint $table) {
            $table->foreignUuid('contribution_proposal_id')->nullable()->unique()->constrained('contribution_proposals')->nullOnDelete();
        });
        DB::statement('alter table conversations drop constraint conversations_origin_check');
        DB::statement("alter table conversations add constraint conversations_origin_check check (origin in ('publication', 'relation', 'contribution'))");

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->foreignUuid('contribution_proposal_id')->nullable()->constrained('contribution_proposals')->cascadeOnDelete();
        });
        $this->rebuildNotificationKinds(NotificationKind::cases());
    }

    public function down(): void
    {
        $this->rebuildNotificationKinds(array_filter(NotificationKind::cases(), fn ($kind) => ! str_starts_with($kind->value, 'contribution_')));

        Schema::table('user_notifications', fn (Blueprint $table) => $table->dropConstrainedForeignId('contribution_proposal_id'));

        DB::statement('alter table conversations drop constraint conversations_origin_check');
        DB::statement("alter table conversations add constraint conversations_origin_check check (origin in ('publication', 'relation'))");
        Schema::table('conversations', fn (Blueprint $table) => $table->dropConstrainedForeignId('contribution_proposal_id'));

        Schema::dropIfExists('contribution_proposals');
        Schema::table('profiles', fn (Blueprint $table) => $table->dropColumn('accepts_contributions'));
        Schema::table('project_members', fn (Blueprint $table) => $table->dropColumn('manages_contributions'));

        DB::statement('alter table publications drop constraint publications_official_check');
        DB::statement('alter table publications drop constraint publications_help_kind_check');
        Schema::table('publications', fn (Blueprint $table) => $table->dropColumn(['title', 'help_kind', 'needed_by', 'official']));
    }

    /** @param  array<int, NotificationKind>  $kinds */
    private function rebuildNotificationKinds(array $kinds): void
    {
        $values = implode(', ', array_map(fn ($kind) => "'".$kind->value."'", $kinds));
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ($values))");
    }
};
