<?php

namespace App\Models;

use App\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Signalement privé : aucune identité de signalant divulguée à l'auteur. */
class PublicationCommentReport extends Model
{
    use HasUuids;

    protected $guarded = ['id', 'comment_id', 'reporter_id', 'resolved_by', 'resolved_at', 'resolution'];

    protected function casts(): array
    {
        return ['category' => ReportCategory::class, 'resolved_at' => 'datetime'];
    }

    public function comment(): BelongsTo
    {
        return $this->belongsTo(PublicationComment::class, 'comment_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function scopeOpen($query)
    {
        return $query->whereNull('resolution');
    }
}
