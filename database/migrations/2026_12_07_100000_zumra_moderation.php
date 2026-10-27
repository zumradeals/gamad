<?php

use App\Enums\NotificationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** D-215 : signaler une ZUMRA ou un message de sa Discussion ; suspension de la ZUMRA et masquage d'un message par la modération. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zumras', function (Blueprint $table) {
            $table->timestamp('suspended_at')->nullable();
            $table->foreignUuid('suspended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('suspension_reason', 500)->nullable();
        });

        Schema::table('zumra_posts', function (Blueprint $table) {
            $table->timestamp('hidden_at')->nullable();
            $table->foreignUuid('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hidden_reason', 500)->nullable();
        });

        Schema::create('zumra_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('zumra_id')->constrained('zumras')->cascadeOnDelete();
            $table->foreignUuid('zumra_post_id')->nullable()->constrained('zumra_posts')->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 24);
            $table->string('details', 280)->nullable();
            $table->string('resolution', 16)->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->index(['resolution', 'created_at']);
        });

        // Une seule signalisation par personne : pour la ZUMRA (sans message), et pour chaque message.
        DB::statement('create unique index zumra_reports_zumra_unique on zumra_reports (zumra_id, reporter_id) where zumra_post_id is null');
        DB::statement('create unique index zumra_reports_post_unique on zumra_reports (zumra_post_id, reporter_id) where zumra_post_id is not null');

        $this->rebuild(NotificationKind::cases());
    }

    public function down(): void
    {
        Schema::dropIfExists('zumra_reports');
        Schema::table('zumra_posts', fn (Blueprint $table) => $table->dropConstrainedForeignId('hidden_by'));
        Schema::table('zumra_posts', fn (Blueprint $table) => $table->dropColumn(['hidden_at', 'hidden_reason']));
        Schema::table('zumras', fn (Blueprint $table) => $table->dropConstrainedForeignId('suspended_by'));
        Schema::table('zumras', fn (Blueprint $table) => $table->dropColumn(['suspended_at', 'suspension_reason']));

        $this->rebuild(array_filter(NotificationKind::cases(), fn ($kind) => ! in_array($kind->value, ['zumra_suspended', 'zumra_reinstated', 'zumra_message_hidden'], true)));
    }

    /** @param  array<int, NotificationKind>  $kinds */
    private function rebuild(array $kinds): void
    {
        $values = implode(', ', array_map(fn ($kind) => "'".$kind->value."'", $kinds));
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ($values))");
    }
};
