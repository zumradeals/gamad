<?php

namespace App\Models;

use App\Enums\ProjectStepState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une étape (jalon) d'un projet : à faire, en cours, franchie. L'ordre est celui de `position`. */
class ProjectStep extends Model
{
    use HasUuids;

    public const TITLE_MIN = 2;

    public const TITLE_MAX = 80;

    /** Nombre d'étapes d'un projet au plus (D-134). */
    public const MAX_PER_PROJECT = 20;

    protected $guarded = ['id', 'project_id', 'state', 'position', 'state_changed_at'];

    protected function casts(): array
    {
        return ['state' => ProjectStepState::class, 'state_changed_at' => 'datetime'];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
