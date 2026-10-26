<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAMAD Publicité V1 (D-209 à D-212), additif : le consentement publicitaire distinct du profil (désactivé par défaut), les campagnes
 * (une annonce par campagne), leur audience (compétences, domaines et villes du référentiel commun, aucun doublon de moteur) et
 * l'historique des décisions de la modération. Aucun montant encaissé : le budget en FCFA est prévisionnel. Rien n'est supprimé.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            // D-211 : ciblage personnalisé sur consentement distinct, désactivé par défaut, révocable.
            $table->boolean('ads_personalization')->default(false);
            $table->timestamp('ads_personalization_at')->nullable();
        });

        Schema::create('ad_campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            // Vide : la personne annonce en son nom ; sinon une entreprise vérifiée qu'elle possède.
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
            $table->string('objective', 10)->nullable();
            $table->string('cta', 10)->nullable();
            $table->string('title', 40)->nullable();
            $table->string('body', 180)->nullable();
            $table->string('image_path')->nullable();
            $table->string('destination_url', 500)->nullable();
            $table->string('audience_mode', 14)->default('personalized');
            $table->boolean('remote')->default(false);
            // Budget prévisionnel en FCFA, entier : aucun paiement n'existe (D-212).
            $table->unsignedInteger('budget_fcfa')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('state', 10)->default('draft');
            $table->string('refusal_reason', 280)->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('moderated_at')->nullable();
            $table->timestamp('stopped_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'state']);
            $table->index(['state', 'submitted_at']);
        });
        DB::statement("alter table ad_campaigns add constraint ad_campaigns_state_check check (state in ('draft', 'review', 'refused', 'ready', 'paused', 'done'))");
        DB::statement("alter table ad_campaigns add constraint ad_campaigns_mode_check check (audience_mode in ('personalized', 'contextual'))");

        Schema::create('ad_campaign_skill', function (Blueprint $table) {
            $table->foreignUuid('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignUuid('skill_id')->constrained('skills')->cascadeOnDelete();
            $table->primary(['ad_campaign_id', 'skill_id']);
        });
        Schema::create('ad_campaign_domain', function (Blueprint $table) {
            $table->foreignUuid('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignUuid('domain_id')->constrained('domains')->cascadeOnDelete();
            $table->primary(['ad_campaign_id', 'domain_id']);
        });
        Schema::create('ad_campaign_city', function (Blueprint $table) {
            $table->foreignUuid('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignUuid('city_id')->constrained('cities')->cascadeOnDelete();
            $table->primary(['ad_campaign_id', 'city_id']);
        });

        Schema::create('ad_moderations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignUuid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 8);
            $table->string('reason', 280)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        DB::statement("alter table ad_moderations add constraint ad_moderations_decision_check check (decision in ('accepted', 'refused'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_moderations');
        Schema::dropIfExists('ad_campaign_city');
        Schema::dropIfExists('ad_campaign_domain');
        Schema::dropIfExists('ad_campaign_skill');
        Schema::dropIfExists('ad_campaigns');
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropColumn(['ads_personalization', 'ads_personalization_at']);
        });
    }
};
