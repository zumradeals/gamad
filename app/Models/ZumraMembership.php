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

    public const DUTY_PENDING = 'pending';

    protected $guarded = ['id', 'zumra_id', 'user_id'];

    protected function casts(): array
    {
        return ['role' => ZumraRole::class, 'duty' => ZumraDuty::class, 'joined_at' => 'datetime'];
    }

    /** Poste proposé par le fondateur, que la personne n'a pas encore accepté : il n'est pas occupé. */
    public function dutyPending(): bool
    {
        return $this->duty !== null && $this->duty_state === self::DUTY_PENDING;
    }

    /** Poste effectif : accepté par la personne, ou antérieur à l'acceptation (sans état). */
    public function dutyEffective(): bool
    {
        return $this->duty !== null && $this->duty_state !== self::DUTY_PENDING;
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
