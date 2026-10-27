<?php

namespace App\Models;

use App\Enums\ReportCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Signalement d'une ZUMRA, ou d'un message de sa Discussion quand `zumra_post_id` est renseigné (D-215). Le signalant n'est jamais montré. */
class ZumraReport extends Model
{
    use HasUuids;

    public const DETAILS_MAX = 280;

    protected $guarded = ['id', 'zumra_id', 'zumra_post_id', 'reporter_id', 'resolution', 'resolved_by', 'resolved_at'];

    protected function casts(): array
    {
        return ['category' => ReportCategory::class, 'resolved_at' => 'datetime'];
    }

    public function zumra(): BelongsTo
    {
        return $this->belongsTo(Zumra::class);
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(ZumraPost::class, 'zumra_post_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolution');
    }
}
