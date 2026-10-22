<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Territoire : ville. `area` regroupe les communes d'un district (« Abidjan, Cocody ») ; il est vide pour les autres villes.
 * Jamais supprimée, seulement désactivée.
 */
class City extends Model
{
    use HasNormalizedName, HasUuids;

    protected $guarded = ['id', 'normalized'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    /** Libellé affiché : « Abidjan, Cocody » ou « Bouaké ». */
    public function label(): string
    {
        return filled($this->area) ? $this->area.', '.$this->name : $this->name;
    }

    public function normalizedSource(): string
    {
        return $this->label();
    }
}
