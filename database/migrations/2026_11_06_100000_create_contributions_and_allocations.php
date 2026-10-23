<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Préférences du titulaire du portefeuille : rappel mensuel de contribution, badge « Contributeur » (D-166, D-170).
        Schema::table('wallets', function (Blueprint $table) {
            $table->boolean('monthly_reminder')->default(false);
            $table->boolean('show_badge')->default(false);
        });

        // Une contribution est versée par le titulaire d'un portefeuille (personne ou ZUMRA), depuis ce portefeuille ou en direct
        // par Mobile Money. Seules les contributions « enregistrées » forment le fonds de contribution (D-165).
        Schema::create('contributions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->string('source', 8);
            $table->unsignedBigInteger('amount');
            $table->string('status', 24)->default('recorded');
            $table->string('environment', 8);
            // Orientation voulue par la personne (D-166) : le fonds commun, une ZUMRA engagée ou un projet. Un souhait, pas un ordre.
            $table->string('target_type', 8)->default('fund');
            $table->uuid('target_id')->nullable();
            $table->uuid('ledger_entry_id')->nullable();
            $table->uuid('payment_id')->nullable()->unique();
            $table->uuid('requested_by')->nullable();
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('decided_at')->nullable();
            $table->string('note', 255)->nullable();
            $table->timestampsTz();

            $table->index(['status', 'environment']);
            $table->index(['wallet_id', 'created_at']);
        });

        DB::statement("alter table contributions add constraint contributions_source_check check (source in ('wallet', 'direct'))");
        DB::statement("alter table contributions add constraint contributions_status_check check (status in ('awaiting_validation', 'recorded', 'refused', 'refunded', 'withdrawn'))");
        DB::statement("alter table contributions add constraint contributions_environment_check check (environment in ('sandbox', 'live'))");
        DB::statement("alter table contributions add constraint contributions_target_check check ((target_type = 'fund' and target_id is null) or (target_type in ('zumra', 'project') and target_id is not null))");
        DB::statement('alter table contributions add constraint contributions_amount_check check (amount > 0)');
        // Une contribution payée depuis le portefeuille a son mouvement au registre ; une contribution directe a son paiement.
        DB::statement("alter table contributions add constraint contributions_source_link_check check (source = 'wallet' or payment_id is not null)");

        // Le fonds est affecté par le Président à des projets de ZUMRA engagées et reconnues (D-167, D-168). Les noms sont copiés
        // pour que le rapport public survive à la disparition d'un projet ou d'une ZUMRA.
        Schema::create('allocations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('zumra_id');
            $table->string('project_name', 120);
            $table->string('zumra_name', 120);
            $table->unsignedBigInteger('amount');
            $table->string('purpose', 255);
            $table->string('status', 8)->default('decided');
            $table->string('environment', 8);
            $table->uuid('decided_by')->nullable();
            $table->timestampTz('paid_at')->nullable();
            $table->string('payment_note', 255)->nullable();
            $table->timestampsTz();

            $table->index(['environment', 'status']);
        });

        DB::statement("alter table allocations add constraint allocations_status_check check (status in ('decided', 'paid'))");
        DB::statement("alter table allocations add constraint allocations_environment_check check (environment in ('sandbox', 'live'))");
        DB::statement('alter table allocations add constraint allocations_amount_check check (amount > 0)');

        // Un paiement peut servir à recharger un portefeuille ou à contribuer en direct (sans toucher au portefeuille).
        Schema::table('payments', function (Blueprint $table) {
            $table->string('purpose', 12)->default('recharge');
            $table->string('target_type', 8)->default('fund');
            $table->uuid('target_id')->nullable();
            $table->uuid('contribution_id')->nullable();
        });

        DB::statement("alter table payments add constraint payments_purpose_check check (purpose in ('recharge', 'contribution'))");
    }

    public function down(): void
    {
        DB::statement('alter table payments drop constraint if exists payments_purpose_check');
        Schema::table('payments', fn (Blueprint $table) => $table->dropColumn(['purpose', 'target_type', 'target_id', 'contribution_id']));
        Schema::dropIfExists('allocations');
        Schema::dropIfExists('contributions');
        Schema::table('wallets', fn (Blueprint $table) => $table->dropColumn(['monthly_reminder', 'show_badge']));
    }
};
