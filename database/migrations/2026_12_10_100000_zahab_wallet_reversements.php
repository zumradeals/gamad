<?php

use App\Enums\NotificationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-218 : reversements du Zahab Wallet. Un reversement est un retrait : GAMAD reverse au titulaire indiqué (son numéro Mobile Money) tout
 * ou partie de son solde. La demande débite tout de suite le registre (au titre « reversement ») ; le Président exécute le versement
 * et le marque comme versé, ou le refuse, et la somme revient au portefeuille (« reversement annulé »). Le registre reste en ajout seul.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table ledger_entries drop constraint ledger_entries_kind_check');
        DB::statement('alter table ledger_entries drop constraint ledger_entries_sign_check');
        DB::statement("alter table ledger_entries add constraint ledger_entries_kind_check check (kind in ('recharge', 'contribution', 'refund', 'payout', 'payout_back'))");
        DB::statement("alter table ledger_entries add constraint ledger_entries_sign_check check ((kind in ('recharge', 'payout_back') and amount > 0) or (kind in ('contribution', 'refund', 'payout') and amount < 0))");

        Schema::create('payouts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('wallet_id')->constrained('wallets')->restrictOnDelete();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('operator', 20);
            // Numéro de réception et nom du titulaire : chiffrés, vus par la personne et par le super administrateur seulement.
            $table->text('phone');
            $table->string('holder_name', 100);
            $table->string('status', 12)->default('requested');
            $table->string('environment', 8);
            $table->uuid('debit_entry_id')->nullable();
            $table->uuid('return_entry_id')->nullable();
            $table->foreignUuid('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('handled_at')->nullable();
            $table->string('provider_reference', 100)->nullable();
            $table->string('decline_reason', 280)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['wallet_id', 'status']);
        });
        DB::statement("alter table payouts add constraint payouts_status_check check (status in ('requested', 'paid', 'declined', 'cancelled'))");
        DB::statement('alter table payouts add constraint payouts_amount_check check (amount > 0)');

        $this->rebuild(NotificationKind::cases());
    }

    public function down(): void
    {
        Schema::dropIfExists('payouts');
        DB::statement('alter table ledger_entries drop constraint ledger_entries_kind_check');
        DB::statement('alter table ledger_entries drop constraint ledger_entries_sign_check');
        DB::statement("alter table ledger_entries add constraint ledger_entries_kind_check check (kind in ('recharge', 'contribution', 'refund'))");
        DB::statement("alter table ledger_entries add constraint ledger_entries_sign_check check ((kind = 'recharge' and amount > 0) or (kind in ('contribution', 'refund') and amount < 0))");
        $this->rebuild(array_filter(NotificationKind::cases(), fn ($kind) => ! in_array($kind->value, ['payout_paid', 'payout_declined'], true)));
    }

    /** @param  array<int, NotificationKind>  $kinds */
    private function rebuild(array $kinds): void
    {
        $values = implode(', ', array_map(fn ($kind) => "'".$kind->value."'", $kinds));
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ($values))");
    }
};
