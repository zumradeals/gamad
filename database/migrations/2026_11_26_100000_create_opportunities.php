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
        Schema::create('opportunities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignUuid('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference', 24)->unique();
            $table->string('kind', 16);
            $table->string('title', 160);
            $table->text('description');
            $table->text('missions')->nullable();
            $table->text('profile')->nullable();
            $table->jsonb('skills')->nullable();
            $table->string('experience', 160)->nullable();
            $table->text('qualifications')->nullable();
            $table->text('conditions')->nullable();
            $table->string('work_mode', 12)->nullable();
            $table->string('workload', 16)->nullable();
            $table->foreignUuid('city_id')->nullable()->constrained('cities')->nullOnDelete();
            $table->foreignUuid('domain_id')->nullable()->constrained('domains')->nullOnDelete();
            $table->date('deadline')->nullable();
            $table->string('apply_mode', 12)->default('gamad');
            $table->string('external_url', 500)->nullable();
            // Texte de recherche sans accents ni majuscules (Normalizer::key), tenu à jour à chaque enregistrement.
            $table->text('search_text')->nullable();
            $table->string('status', 12)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'published_at']);
            $table->index(['company_id', 'status']);
            $table->index('kind');
            $table->index('city_id');
            $table->index('domain_id');
        });
        DB::statement("alter table opportunities add constraint opportunities_kind_check check (kind in ('job', 'internship', 'mission', 'collaboration'))");
        DB::statement("alter table opportunities add constraint opportunities_status_check check (status in ('draft', 'published', 'closed', 'archived'))");
        DB::statement("alter table opportunities add constraint opportunities_work_mode_check check (work_mode is null or work_mode in ('on_site', 'hybrid', 'remote'))");
        DB::statement("alter table opportunities add constraint opportunities_workload_check check (workload is null or workload in ('full_time', 'part_time', 'occasional'))");
        DB::statement("alter table opportunities add constraint opportunities_apply_mode_check check (apply_mode in ('gamad', 'external'))");
        DB::statement("alter table opportunities add constraint opportunities_published_consistent check (status = 'draft' or published_at is not null)");

        Schema::create('opportunity_saves', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['user_id', 'opportunity_id']);
        });

        Schema::create('opportunity_responses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('opportunity_id')->constrained('opportunities')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->text('message')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('state', 12)->default('sent');
            $table->timestamp('seen_at')->nullable();
            $table->timestamps();
            // Une seule réponse par personne et par opportunité : pas de candidature en double.
            $table->unique(['opportunity_id', 'user_id']);
            $table->index(['user_id', 'created_at']);
        });
        DB::statement("alter table opportunity_responses add constraint opportunity_responses_state_check check (state in ('sent', 'seen', 'contacted', 'withdrawn'))");

        Schema::table('user_notifications', function (Blueprint $table) {
            $table->foreignUuid('opportunity_id')->nullable()->constrained('opportunities')->nullOnDelete();
        });
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        $this->addCheck(NotificationKind::cases());
    }

    public function down(): void
    {
        DB::table('user_notifications')->whereIn('kind', ['opportunity_response_received', 'opportunity_response_contacted'])->delete();
        DB::statement('alter table user_notifications drop constraint user_notifications_kind_check');
        $this->addCheck(array_filter(NotificationKind::cases(), fn ($kind) => ! str_starts_with($kind->value, 'opportunity_')));
        Schema::table('user_notifications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('opportunity_id');
        });
        Schema::dropIfExists('opportunity_responses');
        Schema::dropIfExists('opportunity_saves');
        Schema::dropIfExists('opportunities');
    }

    private function addCheck(array $kinds): void
    {
        $values = implode(', ', array_map(fn ($kind) => "'".$kind->value."'", $kinds));
        DB::statement("alter table user_notifications add constraint user_notifications_kind_check check (kind in ($values))");
    }
};
