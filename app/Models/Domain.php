<?php

namespace App\Models;

use App\Models\Concerns\HasNormalizedName;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Domaine d'action (D-069). Jamais supprimé, seulement désactivé. */
class Domain extends Model
{
    use HasNormalizedName, HasUuids;

    protected $guarded = ['id', 'normalized'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
