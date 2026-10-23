<?php

namespace App\Models;

use App\Enums\ProjectState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un changement d'état d'un projet, avec la personne qui l'a fait (vide si son compte n'existe plus). */
class ProjectStateChange extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id', 'project_id'];

    protected function casts(): array
    {
        return ['from_state' => ProjectState::class, 'to_state' => ProjectState::class, 'created_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
