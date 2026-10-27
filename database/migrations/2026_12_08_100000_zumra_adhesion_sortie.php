<?php

use App\Enums\NotificationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * D-216 : mode d'adhésion d'une ZUMRA (libre par défaut, sur approbation au choix du responsable) et fermeture d'une ZUMRA par
 * son fondateur quand il en est le seul membre. Les ZUMRA déjà créées gardent le fonctionnement sur demande qu'elles avaient (D-110).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('zumras', function (Blueprint $table) {
            $table->string('join_mode', 12)->default('open');
            $table->timestamp('closed_at')->nullable();
        });

        DB::table('zumras')->update(['join_mode' => 'approval']);
        DB::statement("alter table zumras add constraint zumras_join_mode_check check (join_mode in ('open', 'approval'))");

        $this->rebuild(NotificationKind::cases());
    }

    public function down(): void
    {
        DB::statement('alter table zumras drop constraint zumras_join_mode_check');
        Schema::table('zumras', fn (Blueprint $table) => $table->dropColumn(['join_mode', 'closed_at']));

        $this->rebuild(array_filter(NotificationKind::cases(), fn ($kind) => ! in_array($kind->value, ['zumra_member_joined', 'zumra_founder_given'], true)));
    }

    /** @param  array<int, NotificationKind>  $kinds */
    private function rebuild(array $kinds): void
    {
        $values = implode(', ', array_map(fn ($kind) => "'".$kind->value."'", $kinds));
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ($values))");
    }
};
