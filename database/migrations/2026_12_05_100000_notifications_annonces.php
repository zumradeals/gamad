<?php

use App\Enums\NotificationKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/** GAMAD Publicité (D-212) : l'annonceur est averti de la décision de la modération. Reprend la liste des types de notification. */
return new class extends Migration
{
    public function up(): void
    {
        $this->rebuild(NotificationKind::cases());
    }

    public function down(): void
    {
        $this->rebuild(array_filter(NotificationKind::cases(), fn ($kind) => ! str_starts_with($kind->value, 'ad_')));
    }

    /** @param  array<int, NotificationKind>  $kinds */
    private function rebuild(array $kinds): void
    {
        $values = implode(', ', array_map(fn ($kind) => "'".$kind->value."'", $kinds));
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ($values))");
    }
};
