<?php

namespace App\Models;

use App\Enums\ZumraDuty;
use App\Enums\ZumraRole;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une personne admise dans une ZUMRA, avec son rôle. À ne pas confondre avec « membre du Mouvement GAMAD », non géré ici. */
class ZumraMembership extends Model
{
    use HasUuids;

    protected $guarded = ['id', 'zumra_id', 'user_id'];

    protected function casts(): array
    {
        return ['role' => ZumraRole::class, 'duty' => ZumraDuty::class, 'joined_at' => 'datetime'];
    }

    public function zumra(): BelongsTo
    {
        return $this->belongsTo(Zumra::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
