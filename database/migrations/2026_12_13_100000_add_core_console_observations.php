<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_access_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestampTz('occurred_at', 6)->index();
            $table->string('outcome', 16);
            $table->string('source_key', 64)->nullable();
            $table->string('target_key', 64)->nullable();
            // Deliberately not FKs: security evidence survives account closure until its short retention expires.
            $table->uuid('authenticated_id')->nullable();
            $table->uuid('target_id')->nullable();
            $table->boolean('sensitive_target')->default(false);
            $table->text('ip')->nullable();
            $table->text('user_agent')->nullable();
            $table->index(['source_key', 'occurred_at']);
            $table->index(['target_key', 'occurred_at']);
            $table->index(['target_id', 'occurred_at']);
            $table->index(['outcome', 'occurred_at']);
        });
        Schema::create('security_access_hours', function (Blueprint $table) {
            $table->timestampTz('hour')->primary();
            $table->unsignedBigInteger('success')->default(0);
            $table->unsignedBigInteger('failure')->default(0);
            $table->unsignedBigInteger('limited')->default(0);
            $table->unsignedBigInteger('refused')->default(0);
        });
        // Runner observations use the existing execution journal, with an explicit prefix and numeric metrics.
        Schema::table('execution_runs', function (Blueprint $table) {
            $table->index(['job', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::table('execution_runs', fn (Blueprint $table) => $table->dropIndex(['job', 'ran_at']));
        Schema::dropIfExists('security_access_hours');
        Schema::dropIfExists('security_access_events');
    }
};
