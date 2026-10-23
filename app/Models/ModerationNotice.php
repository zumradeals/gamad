<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Avis de la modération : le droit de s'expliquer qui précède toute suspension d'une personne (PRODUIT §6, règle 6, D-145).
 * Il reste ouvert tant que la modération ne l'a pas classé ou suivi d'une suspension.
 */
class ModerationNotice extends Model
{
    use HasUuids;

    public const SUSPENDED = 'suspended';

    public const CLOSED = 'closed';

    protected $guarded = ['id', 'user_id', 'issued_by'];

    protected function casts(): array
    {
        return ['explained_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function isOpen(): bool
    {
        return $this->resolved_at === null;
    }

    public function isExplained(): bool
    {
        return $this->explained_at !== null;
    }

    /** Fin du délai laissé à la personne pour s'expliquer. */
    public function deadline(): Carbon
    {
        return $this->created_at->copy()->addDays((int) config('gamad.moderation.explanation_days'));
    }

    /** La modération peut suspendre : la personne s'est expliquée, ou le délai est écoulé sans réponse. */
    public function readyForDecision(): bool
    {
        return $this->isOpen() && ($this->isExplained() || $this->deadline()->lte(now()));
    }
}
