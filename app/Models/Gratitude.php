<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Remerciement (PRODUIT : Gratitude) : message public par lequel l'auteur d'un besoin dit qui l'a aidé (D-084). Aucun compteur. */
class Gratitude extends Model
{
    use HasUuids;

    public const BODY_MAX = 280;

    protected $guarded = ['id', 'publication_id', 'author_id', 'helper_id'];

    protected function casts(): array
    {
        return ['hidden_at' => 'datetime'];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /** La personne qui remercie. */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** La personne remerciée. */
    public function helper(): BelongsTo
    {
        return $this->belongsTo(User::class, 'helper_id');
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }
}
