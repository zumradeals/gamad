<?php

namespace App\Models;

use App\Enums\JoinRequestState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Demande d'adhésion : une personne demande à entrer dans une ZUMRA, le fondateur ou un responsable décide (D-110). */
class JoinRequest extends Model
{
    use HasUuids;

    public const MESSAGE_MAX = 280;

    protected $guarded = ['id', 'zumra_id', 'user_id'];

    protected function casts(): array
    {
        return ['state' => JoinRequestState::class, 'decided_at' => 'datetime'];
    }

    public function zumra(): BelongsTo
    {
        return $this->belongsTo(Zumra::class);
    }

    /** La personne qui demande. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->state === JoinRequestState::Pending;
    }
}
