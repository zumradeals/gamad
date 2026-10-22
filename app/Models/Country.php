<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Territoire : pays (D-068). Jamais supprimé, seulement désactivé. */
class Country extends Model
{
    use HasNormalizedName, HasUuids;

    protected $guarded = ['id', 'normalized'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function cities(): HasMany
    {
        return $this->hasMany(City::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
