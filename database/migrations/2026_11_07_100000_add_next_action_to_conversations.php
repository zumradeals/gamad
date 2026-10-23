<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->string('next_action', 280)->nullable();
            $table->date('next_action_date')->nullable();
            $table->foreignUuid('next_action_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('next_action_confirmed_at')->nullable();
            $table->unsignedInteger('next_action_revision')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['next_action_by']);
            $table->dropColumn(['next_action', 'next_action_date', 'next_action_by', 'next_action_confirmed_at', 'next_action_revision']);
        });
    }
};
