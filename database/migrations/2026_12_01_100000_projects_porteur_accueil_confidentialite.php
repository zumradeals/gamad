<?php

use App\Enums\NotificationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * P1 (D-206), additif : un projet a un porteur, il est privé par défaut, il est accueilli par une ZUMRA qui doit l'accepter, et il peut
 * publier une présentation publique limitée. Rien n'est supprimé : les projets existants gardent leur ZUMRA (accueil « accepté »), leur
 * porteur est la personne qui les a créés, et ils restent privés. Trois tables additives : compétences recherchées, invitations de
 * collaboration (consentement) et liens avec une entreprise (traçabilité, sans workflow juridique).
 *
 * Reprend aussi la liste des types de notification : la contrainte n'avait pas suivi les ajouts de Z1 (postes de gouvernance).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->foreignUuid('owner_id')->nullable()->after('zumra_id')->constrained('users')->nullOnDelete();
            $table->foreignUuid('domain_id')->nullable()->constrained('domains')->nullOnDelete();
            $table->foreignUuid('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->string('stage', 12)->default('idea');
            // Accueil par la ZUMRA : pending, accepted, declined, withdrawn. Les projets d'avant P1 sont déjà accueillis.
            $table->string('hosting_state', 10)->default('accepted');
            $table->string('hosting_message', 280)->nullable();
            $table->timestamp('hosting_requested_at')->nullable();
            $table->timestamp('hosting_decided_at')->nullable();
            $table->foreignUuid('hosting_decided_by')->nullable()->constrained('users')->nullOnDelete();
            // Lien historique avec la première ZUMRA qui a accueilli le projet : conservé, même si le projet change de ZUMRA ou devient entreprise.
            $table->foreignUuid('origin_zumra_id')->nullable()->constrained('zumras')->nullOnDelete();
            // Présentation publique limitée : seulement ce que le porteur a choisi de publier. Vide = rien de public.
            $table->timestamp('showcase_published_at')->nullable();
            $table->string('showcase_summary', 300)->nullable();
            $table->string('showcase_contribute', 500)->nullable();
            $table->boolean('showcase_show_owner')->default(false);
            $table->boolean('showcase_show_needs')->default(false);
        });

        DB::statement("alter table projects add constraint projects_stage_check check (stage in ('idea', 'preparation', 'development', 'launch', 'activity'))");
        DB::statement("alter table projects add constraint projects_hosting_check check (hosting_state in ('pending', 'accepted', 'declined', 'withdrawn'))");
        DB::statement('alter table projects add constraint projects_showcase_consistent check (showcase_published_at is null or showcase_summary is not null)');

        // Reprise des projets existants : porteur = la personne qui les a créés, ZUMRA d'origine = leur ZUMRA, étape selon l'état.
        DB::statement('update projects set owner_id = (select user_id from project_state_changes c where c.project_id = projects.id and c.from_state is null order by c.created_at limit 1)');
        DB::statement('update projects set origin_zumra_id = zumra_id');
        DB::statement('update projects set domain_id = (select domain_id from zumras z where z.id = projects.zumra_id), city_id = (select city_id from zumras z where z.id = projects.zumra_id)');
        DB::statement("update projects set stage = case state when 'in_progress' then 'development' when 'done' then 'activity' else 'idea' end");

        Schema::create('project_skill', function (Blueprint $table) {
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->primary(['project_id', 'skill_id']);
            $table->index('skill_id');
        });

        Schema::create('project_invitations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('state', 10)->default('pending');
            $table->string('message', 280)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['user_id', 'state']);
        });
        DB::statement("alter table project_invitations add constraint project_invitations_state_check check (state in ('pending', 'accepted', 'refused', 'cancelled'))");

        Schema::create('project_company_links', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('kind', 10);
            $table->string('state', 10)->default('requested');
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('origin_zumra_id')->nullable()->constrained('zumras')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'company_id']);
            $table->index(['company_id', 'state']);
        });
        DB::statement("alter table project_company_links add constraint project_company_links_kind_check check (kind in ('carried', 'supported'))");
        DB::statement("alter table project_company_links add constraint project_company_links_state_check check (state in ('requested', 'confirmed', 'declined', 'ended'))");

        $this->rebuildNotificationKinds(NotificationKind::cases());
    }

    public function down(): void
    {
        $new = ['project_hosting_asked', 'project_hosting_accepted', 'project_hosting_declined', 'project_invited', 'project_invite_accepted', 'project_company_asked', 'project_company_answered'];
        DB::table('user_notifications')->whereIn('kind', $new)->delete();
        $this->rebuildNotificationKinds(array_filter(NotificationKind::cases(), fn ($kind) => ! in_array($kind->value, $new, true)));

        Schema::dropIfExists('project_company_links');
        Schema::dropIfExists('project_invitations');
        Schema::dropIfExists('project_skill');

        DB::statement('alter table projects drop constraint if exists projects_showcase_consistent');
        DB::statement('alter table projects drop constraint if exists projects_hosting_check');
        DB::statement('alter table projects drop constraint if exists projects_stage_check');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hosting_decided_by');
            $table->dropConstrainedForeignId('origin_zumra_id');
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('domain_id');
            $table->dropConstrainedForeignId('owner_id');
            $table->dropColumn(['stage', 'hosting_state', 'hosting_message', 'hosting_requested_at', 'hosting_decided_at', 'showcase_published_at', 'showcase_summary', 'showcase_contribute', 'showcase_show_owner', 'showcase_show_needs']);
        });
    }

    /** @param  array<int, NotificationKind>  $kinds */
    private function rebuildNotificationKinds(array $kinds): void
    {
        $values = implode(', ', array_map(fn ($kind) => "'".$kind->value."'", $kinds));
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ($values))");
    }
};
