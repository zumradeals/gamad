<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Message de la Discussion d'une ZUMRA (glossaire : ZumraThread) : texte brut de 1 000 caractères au plus, réservé aux membres (D-021, D-112). */
class ZumraPost extends Model
{
    use HasUuids;

    public const BODY_MAX = 1000;

    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id', 'zumra_id', 'user_id', 'hidden_at', 'hidden_by', 'hidden_reason'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'hidden_at' => 'datetime'];
    }

    public function zumra(): BelongsTo
    {
        return $this->belongsTo(Zumra::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ZumraReport::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
