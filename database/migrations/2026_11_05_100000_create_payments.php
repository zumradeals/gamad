<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Une recharge de portefeuille par le prestataire de paiement (D-169). L'identifiant de cette ligne est la référence que
        // GAMAD envoie au prestataire (external_reference) : rejouer une création ne peut pas produire deux paiements.
        Schema::create('payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();
            // La personne qui a lancé la recharge ; pas de clé étrangère : la ligne survit au compte.
            $table->uuid('initiated_by')->nullable();
            $table->string('provider_reference', 60)->nullable()->unique();
            $table->unsignedBigInteger('amount');
            $table->string('environment', 8);
            $table->string('status', 12)->default('pending');
            $table->text('checkout_url')->nullable();
            // Mouvements du registre produits par ce paiement : crédit à la confirmation, débit à un remboursement.
            $table->uuid('credit_entry_id')->nullable();
            $table->uuid('refund_entry_id')->nullable();
            // Anomalie à examiner par le Président (montant différent, plafond dépassé, remboursement sans solde…).
            $table->string('note', 255)->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'created_at']);
            $table->index('wallet_id');
        });

        DB::statement("alter table payments add constraint payments_status_check check (status in ('pending', 'completed', 'failed', 'cancelled', 'refunded', 'expired'))");
        DB::statement("alter table payments add constraint payments_environment_check check (environment in ('sandbox', 'live'))");
        DB::statement('alter table payments add constraint payments_amount_check check (amount > 0)');

        // Un même paiement du prestataire, ou un même motif, ne produit jamais deux mouvements du même type.
        DB::statement('create unique index ledger_entries_kind_reference_unique on ledger_entries (kind, reference) where reference is not null');
    }

    public function down(): void
    {
        DB::statement('drop index if exists ledger_entries_kind_reference_unique');
        Schema::dropIfExists('payments');
    }
};
