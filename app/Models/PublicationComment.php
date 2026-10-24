<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** D-193 : texte brut, une seule profondeur de réponses, aucune appréciation ni note. */
class PublicationComment extends Model
{
    use HasUuids;

    public const BODY_MAX = 500;

    protected $guarded = ['id', 'publication_id', 'author_id', 'parent_id', 'hidden_at', 'hidden_by', 'hidden_reason', 'deleted_at', 'author_explanation', 'explained_at'];

    protected function casts(): array
    {
        return ['deleted_at' => 'datetime', 'hidden_at' => 'datetime', 'explained_at' => 'datetime'];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('created_at')->orderBy('id');
    }

    public function reports(): HasMany
    {
        return $this->hasMany(PublicationCommentReport::class, 'comment_id');
    }

    public function isVisible(): bool
    {
        return ! $this->deleted_at && ! $this->hidden_at && $this->author?->isActive();
    }
}
