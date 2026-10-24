<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** D-193 : conversations publiques distinctes des gestes et des conversations privées. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_comments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('parent_id')->nullable()->constrained('publication_comments')->nullOnDelete();
            $table->string('body', 500)->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('hidden_at')->nullable();
            $table->foreignUuid('hidden_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('hidden_reason', 500)->nullable();
            $table->string('author_explanation', 500)->nullable();
            $table->timestamp('explained_at')->nullable();
            $table->timestamps();
            $table->index(['publication_id', 'parent_id', 'created_at']);
            $table->index(['hidden_at', 'created_at']);
        });

        Schema::create('publication_comment_reports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('comment_id')->constrained('publication_comments')->cascadeOnDelete();
            $table->foreignUuid('reporter_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('category', 24);
            $table->string('details', 280)->nullable();
            $table->string('resolution', 16)->nullable();
            $table->foreignUuid('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['comment_id', 'reporter_id']);
            $table->index(['resolution', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_comment_reports');
        Schema::dropIfExists('publication_comments');
    }
};
