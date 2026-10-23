<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pas de clé étrangère vers le titulaire : le registre survit à la suppression d'un compte ou d'une ZUMRA (comme le journal
        // d'administration). Un portefeuille n'est fermé que lorsque son solde est nul (D-172).
        Schema::create('wallets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('owner_type', 8);
            $table->uuid('owner_id');
            $table->timestampTz('closed_at')->nullable();
            $table->timestampsTz();

            $table->unique(['owner_type', 'owner_id']);
        });

        DB::statement("alter table wallets add constraint wallets_owner_type_check check (owner_type in ('person', 'zumra'))");

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->unsignedBigInteger('sequence');
            $table->string('kind', 16);
            // En ZAHAB, entiers (1 ZAHAB = 1 FCFA) : positif pour un crédit, négatif pour un débit.
            $table->bigInteger('amount');
            $table->bigInteger('balance_after');
            // Référence extérieure (paiement du prestataire) quand il y en a une.
            $table->string('reference', 120)->nullable();
            $table->uuid('actor_id')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->unique(['wallet_id', 'sequence']);
            $table->index(['wallet_id', 'created_at']);
        });

        DB::statement("alter table ledger_entries add constraint ledger_entries_kind_check check (kind in ('recharge', 'contribution', 'refund'))");
        DB::statement('alter table ledger_entries add constraint ledger_entries_amount_check check (amount <> 0)');
        DB::statement('alter table ledger_entries add constraint ledger_entries_balance_check check (balance_after >= 0)');
        DB::statement('alter table ledger_entries add constraint ledger_entries_sequence_check check (sequence >= 1)');
        // Une recharge crédite ; une contribution et un remboursement débitent.
        DB::statement("alter table ledger_entries add constraint ledger_entries_sign_check check ((kind = 'recharge' and amount > 0) or (kind in ('contribution', 'refund') and amount < 0))");

        // Le registre est en ajout seul : la base refuse toute modification et tout effacement.
        DB::unprepared(<<<'SQL'
            create or replace function ledger_entries_append_only() returns trigger as $$
            begin
                raise exception 'Le registre des portefeuilles est en ajout seul : modification et suppression interdites.';
            end;
            $$ language plpgsql
        SQL);

        DB::unprepared(<<<'SQL'
            create trigger ledger_entries_no_update_delete
            before update or delete on ledger_entries
            for each row execute function ledger_entries_append_only()
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists ledger_entries_no_update_delete on ledger_entries');
        DB::unprepared('drop function if exists ledger_entries_append_only()');
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('wallets');
    }
};
