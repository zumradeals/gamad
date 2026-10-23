<?php

namespace App\Models;

use App\Enums\ProjectState;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Projet : une action structurée, toujours rattachée à une ZUMRA, avec une page publique (PRODUIT, D-005). Domaine et lieu sont
 * ceux de la ZUMRA.
 */
class Project extends Model
{
    use HasUuids;

    public const NAME_MIN = 3;

    public const NAME_MAX = 80;

    public const GOAL_MAX = 500;

    protected $guarded = ['id', 'zumra_id', 'name_key', 'search', 'state', 'state_changed_at'];

    protected function casts(): array
    {
        return ['state' => ProjectState::class, 'state_changed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::saving(function (Project $project) {
            $project->name_key = Normalizer::key($project->name);
            $project->search = Normalizer::key($project->name.' '.$project->goal);
        });
    }

    public function zumra(): BelongsTo
    {
        return $this->belongsTo(Zumra::class);
    }

    /** Les étapes, dans l'ordre. */
    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class)->orderBy('position');
    }

    /** Les avancées et réalisations publiées au nom du projet. */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function stateChanges(): HasMany
    {
        return $this->hasMany(ProjectStateChange::class);
    }
}
