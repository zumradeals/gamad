<?php

namespace App\Models;

use App\Enums\InvitationState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Invitation à rejoindre une ZUMRA, trouvée par capacité. Elle expire après 14 jours (D-110). */
class Invitation extends Model
{
    use HasUuids;

    public const MESSAGE_MAX = 280;

    public const VALID_DAYS = 14;

    protected $guarded = ['id', 'zumra_id', 'user_id'];

    protected function casts(): array
    {
        return ['state' => InvitationState::class, 'expires_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function zumra(): BelongsTo
    {
        return $this->belongsTo(Zumra::class);
    }

    /** La personne invitée. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Celle qui l'a envoyée (vide si son compte n'existe plus). */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /** En attente et pas encore expirée : seule une invitation ouverte peut être acceptée. */
    public function isOpen(): bool
    {
        return $this->state === InvitationState::Pending && $this->expires_at->isFuture();
    }

    /** Invitations ouvertes : en attente et non expirées. */
    public function scopeOpen($query)
    {
        return $query->where('state', InvitationState::Pending->value)->where('expires_at', '>', now());
    }
}
