<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('owner_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('slug', 190)->unique();
            $table->string('name', 160);
            $table->string('legal_name', 160);
            $table->string('registration_country', 2);
            $table->string('registration_number', 100);
            $table->string('sector', 120);
            $table->string('city', 120)->nullable();
            $table->string('website')->nullable();
            $table->string('email')->nullable();
            $table->text('summary')->nullable();
            $table->text('description')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignUuid('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->unique(['registration_country', 'registration_number'], 'companies_registration_unique');
            $table->index(['status', 'name']);
        });

        Schema::create('company_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('private_path');
            $table->string('extension', 8);
            $table->timestamps();
            $table->unique(['company_id', 'kind']);
        });

        Schema::create('company_follows', function (Blueprint $table) {
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->cascadeOnDelete();
            $table->timestamp('created_at');
            $table->primary(['user_id', 'company_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_follows');
        Schema::dropIfExists('company_documents');
        Schema::dropIfExists('companies');
    }
};
