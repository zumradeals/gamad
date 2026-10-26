<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lot D2 : une piste peut désigner une opportunité (D-202). Additif : les pistes existantes (personne ou offre) restent valides sans
 * changement ; une piste vise exactement une cible parmi personne, offre et opportunité, et seule une piste d'opportunité n'a pas de
 * publication.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table match_suggestions drop constraint match_suggestions_target');
        DB::statement('alter table match_suggestions alter column publication_id drop not null');

        Schema::table('match_suggestions', function (Blueprint $table) {
            $table->foreignUuid('opportunity_id')->nullable()->after('offer_id')->constrained()->cascadeOnDelete();
        });

        DB::statement('alter table match_suggestions add constraint match_suggestions_target check (num_nonnulls(profile_id, offer_id, opportunity_id) = 1)');
        DB::statement('alter table match_suggestions add constraint match_suggestions_publication check ((opportunity_id is null) = (publication_id is not null))');
    }

    public function down(): void
    {
        DB::table('match_suggestions')->whereNotNull('opportunity_id')->delete();
        DB::statement('alter table match_suggestions drop constraint match_suggestions_publication');
        DB::statement('alter table match_suggestions drop constraint match_suggestions_target');

        Schema::table('match_suggestions', fn (Blueprint $table) => $table->dropConstrainedForeignId('opportunity_id'));

        DB::statement('alter table match_suggestions alter column publication_id set not null');
        DB::statement('alter table match_suggestions add constraint match_suggestions_target check ((profile_id is null) <> (offer_id is null))');
    }
};
