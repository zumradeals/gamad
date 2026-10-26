<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Invitation à collaborer à un projet (P1) : le porteur propose, la personne accepte ou refuse. Rien ne se fait sans son consentement. */
class ProjectInvitation extends Model
{
    use HasUuids;

    public const MESSAGE_MAX = 280;

    public const PENDING = 'pending';

    public const ACCEPTED = 'accepted';

    public const REFUSED = 'refused';

    public const CANCELLED = 'cancelled';

    protected $guarded = ['id', 'project_id', 'user_id', 'invited_by', 'state', 'decided_at'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** La personne invitée. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->state === self::PENDING;
    }
}
