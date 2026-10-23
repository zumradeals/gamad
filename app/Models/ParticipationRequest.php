<?php

namespace App\Models;

use App\Enums\JoinRequestState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Demande « Je veux participer » : un membre de la ZUMRA demande à entrer dans l'équipe d'un projet ; ses responsables décident (D-128). */
class ParticipationRequest extends Model
{
    use HasUuids;

    public const MESSAGE_MAX = 280;

    protected $guarded = ['id', 'project_id', 'user_id'];

    protected function casts(): array
    {
        return ['state' => JoinRequestState::class, 'decided_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
