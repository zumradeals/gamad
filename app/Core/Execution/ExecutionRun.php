<?php

namespace App\Core\Execution;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Une tentative d'exécution d'une tâche : durée, issue, résultats chiffrés. Aucun sujet, aucun contenu, aucune personne. */
class ExecutionRun extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'ran_at' => 'datetime'];
    }
}
