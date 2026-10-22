<?php

use App\Support\Normalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Profil d'une personne (Lot 1) : présentation, territoire, visibilité, photo ; capacités partagées et liens
 * « apporte » / « apprend » ; domaines d'intérêt. Les limites (10 capacités par sens, 3 domaines) sont tenues par
 * l'application, sous verrou du profil (voir App\Services\ProfileEditor).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skills', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 60);
            $table->string('normalized', 120)->unique();
            $table->timestamps();
        });

        Schema::create('profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained('users')->cascadeOnDelete();
            // Identifiant public dans l'adresse /personnes/{identifiant} : aléatoire, sans lien avec l'e-mail ni le nom.
            $table->string('public_id', 16)->unique();
            $table->string('bio', 280)->nullable();
            $table->foreignUuid('country_id')->nullable()->constrained('countries')->restrictOnDelete();
            $table->foreignUuid('city_id')->nullable()->constrained('cities')->restrictOnDelete();
            $table->string('visibility', 16)->default('public');
            $table->string('photo_path')->nullable();
            $table->timestamps();
        });

        DB::statement("alter table profiles add constraint profiles_visibility_check check (visibility in ('public', 'members'))");

        Schema::create('profile_skill', function (Blueprint $table) {
            $table->uuid('profile_id');
            $table->uuid('skill_id');
            $table->string('kind', 8);
            $table->unsignedSmallInteger('position')->default(0);

            $table->primary(['profile_id', 'skill_id', 'kind']);
            $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
            $table->foreign('skill_id')->references('id')->on('skills')->restrictOnDelete();
            $table->index(['kind', 'skill_id']);
        });

        DB::statement("alter table profile_skill add constraint profile_skill_kind_check check (kind in ('offered', 'wanted'))");

        Schema::create('domain_profile', function (Blueprint $table) {
            $table->uuid('profile_id');
            $table->uuid('domain_id');

            $table->primary(['profile_id', 'domain_id']);
            $table->foreign('profile_id')->references('id')->on('profiles')->cascadeOnDelete();
            $table->foreign('domain_id')->references('id')->on('domains')->restrictOnDelete();
        });

        // Recherche par nom sans casse ni accents : copie normalisée du nom affiché, tenue à jour par le modèle User.
        Schema::table('users', function (Blueprint $table) {
            $table->string('name_search', 300)->default('');
        });

        // Les personnes déjà inscrites reçoivent leur profil et leur clé de recherche.
        foreach (DB::table('users')->get(['id', 'name']) as $user) {
            DB::table('users')->where('id', $user->id)->update(['name_search' => Normalizer::key($user->name)]);

            DB::table('profiles')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'public_id' => Str::lower(Str::random(12)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('name_search'));
        Schema::dropIfExists('domain_profile');
        Schema::dropIfExists('profile_skill');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('skills');
    }
};
