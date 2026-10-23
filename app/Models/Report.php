<?php

namespace App\Models;

use App\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Signalement : alerte envoyée par une personne sur une publication. L'auteur ne sait jamais qui a signalé (écran 16). */
class Report extends Model
{
    use HasUuids;

    public const DETAILS_MAX = 280;

    public const HIDDEN = 'hidden';

    public const DISMISSED = 'dismissed';

    protected $guarded = ['id', 'publication_id', 'reporter_id'];

    protected function casts(): array
    {
        return ['category' => ReportCategory::class, 'resolved_at' => 'datetime'];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
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
