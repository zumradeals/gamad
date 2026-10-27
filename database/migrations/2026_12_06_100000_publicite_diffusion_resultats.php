<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * GAMAD Publicité V1 (D-212), additif : le mécanisme de diffusion et les résultats. Aucun compteur ne se rattache à une personne vue : seuls
 * des totaux par campagne et par jour sont lus par l'annonceur. `ad_impressions` ne sert qu'à n'afficher une annonce qu'une fois par jour et
 * par personne ; `ad_hides` et `ad_reports` sont les gestes de la personne qui voit l'annonce (masquer, signaler). Rien ne s'écrit tant que la
 * diffusion payante est fermée.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_daily_stats', function (Blueprint $table) {
            $table->foreignUuid('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->date('day');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('interactions')->default(0);
            $table->unsignedInteger('contacts')->default(0);
            $table->primary(['ad_campaign_id', 'day']);
        });

        Schema::create('ad_impressions', function (Blueprint $table) {
            $table->foreignUuid('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('day');
            $table->primary(['ad_campaign_id', 'user_id', 'day']);
        });

        Schema::create('ad_hides', function (Blueprint $table) {
            $table->foreignUuid('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['ad_campaign_id', 'user_id']);
        });

        Schema::create('ad_reports', function (Blueprint $table) {
            $table->foreignUuid('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('reason', 12);
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['ad_campaign_id', 'user_id']);
        });
        DB::statement("alter table ad_reports add constraint ad_reports_reason_check check (reason in ('misleading', 'offensive', 'forbidden', 'privacy', 'spam', 'other'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_reports');
        Schema::dropIfExists('ad_hides');
        Schema::dropIfExists('ad_impressions');
        Schema::dropIfExists('ad_daily_stats');
    }
};
