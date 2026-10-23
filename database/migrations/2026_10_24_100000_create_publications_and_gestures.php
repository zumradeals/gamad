<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Publications typées et gestes (Lot 2a, D-081 à D-087). Une publication est toujours celle d'une personne ; un besoin
 * est une publication de type « need » qui porte un état. La suppression d'un compte supprime ses publications et ses
 * gestes (D-088).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('body', 500);
            $table->foreignUuid('domain_id')->nullable()->constrained('domains')->restrictOnDelete();
            $table->foreignUuid('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->string('visibility', 16)->default('public');
            // Seulement pour un besoin : ouvert, en cours, résolu, fermé sans suite.
            $table->string('need_state', 16)->nullable();
            $table->timestamps();

            $table->index(['created_at', 'id']);
            $table->index(['author_id', 'created_at']);
            $table->index('type');
        });

        DB::statement("alter table publications add constraint publications_type_check check (type in ('need', 'knowledge', 'learning_wish', 'offer'))");
        DB::statement("alter table publications add constraint publications_visibility_check check (visibility in ('public', 'members'))");
        DB::statement("alter table publications add constraint publications_need_state_check check (need_state is null or need_state in ('open', 'in_progress', 'resolved', 'closed'))");
        // Un besoin a toujours un état ; les autres types n'en ont jamais.
        DB::statement("alter table publications add constraint publications_need_state_only_for_needs check ((type = 'need') = (need_state is not null))");

        Schema::create('publication_skill', function (Blueprint $table) {
            $table->uuid('publication_id');
            $table->uuid('skill_id');
            $table->unsignedSmallInteger('position')->default(0);

            $table->primary(['publication_id', 'skill_id']);
            $table->foreign('publication_id')->references('id')->on('publications')->cascadeOnDelete();
            $table->foreign('skill_id')->references('id')->on('skills')->restrictOnDelete();
        });

        Schema::create('gestures', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('publication_id')->constrained('publications')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('message', 280)->nullable();
            $table->string('state', 16)->default('sent');
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            // Une personne n'a qu'un geste par publication (D-082).
            $table->unique(['publication_id', 'user_id']);
            $table->index(['user_id', 'state']);
        });

        DB::statement("alter table gestures add constraint gestures_state_check check (state in ('sent', 'accepted', 'declined', 'withdrawn'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('gestures');
        Schema::dropIfExists('publication_skill');
        Schema::dropIfExists('publications');
    }
};
