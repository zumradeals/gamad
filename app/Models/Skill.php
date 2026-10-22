<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Capacité (D-066) : étiquette de savoir-faire partagée par tous, unique sans casse, accents ni espaces superflus. */
class Skill extends Model
{
    use HasNormalizedName, HasUuids;

    public const MIN_LENGTH = 2;

    public const MAX_LENGTH = 60;

    protected $guarded = ['id', 'normalized'];

    /**
     * La capacité qui porte ce nom, créée si elle n'existe pas encore. Deux personnes qui l'écrivent au même instant
     * obtiennent la même capacité : l'insertion ignore le doublon (sans faire échouer la transaction en cours).
     */
    public static function findOrCreateByName(string $name): self
    {
        $display = Normalizer::display($name);
        $key = Normalizer::key($display);

        DB::table('skills')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'name' => $display,
            'normalized' => $key,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return static::query()->where('normalized', $key)->firstOrFail();
    }
}
