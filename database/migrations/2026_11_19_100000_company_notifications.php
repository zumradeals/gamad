<?php

use App\Enums\NotificationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->foreignUuid('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->foreignUuid('company_experience_id')->nullable()->constrained('company_experiences')->nullOnDelete();
        });
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        $this->checkKinds();
    }

    public function down(): void
    {
        DB::table('user_notifications')->whereIn('kind', [
            'company_exp_requested', 'company_exp_confirmed', 'company_exp_declined',
            'company_review_updated', 'company_review_requested', 'company_report_received',
        ])->delete();

        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        $original = array_filter(NotificationKind::cases(), fn ($kind) => ! str_starts_with($kind->value, 'company_'));
        $this->addCheck($original);

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_experience_id');
            $table->dropConstrainedForeignId('company_id');
        });
    }

    private function checkKinds(): void
    {
        $this->addCheck(NotificationKind::cases());
    }

    private function addCheck(array $kinds): void
    {
        $values = implode(', ', array_map(fn ($kind) => "'".$kind->value."'", $kinds));
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ($values))");
    }
};
