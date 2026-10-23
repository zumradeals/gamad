<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une personne de l'équipe d'un projet : un membre de la ZUMRA du projet, ajouté par ses responsables ou admis sur demande (D-128). */
class ProjectMember extends Model
{
    use HasUuids;

    protected $guarded = ['id', 'project_id', 'user_id', 'added_by'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime'];
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
