<?php

namespace App\Core\Files;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Un fichier à effacer une fois la transaction qui l'a rendu inutile validée. Voir FilePurger. */
class FilePurge extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'last_attempt_at' => 'datetime'];
    }
}
