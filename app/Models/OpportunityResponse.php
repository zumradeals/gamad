<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/** Réponse privée d'une personne à une opportunité : visible d'elle et de l'entreprise émettrice, de personne d'autre. */
class OpportunityResponse extends Model
{
    use HasUuids;

    public const SENT = 'sent';

    public const SEEN = 'seen';

    public const CONTACTED = 'contacted';

    public const WITHDRAWN = 'withdrawn';

    protected $guarded = ['id', 'opportunity_id', 'user_id', 'attachment_path'];

    protected function casts(): array
    {
        return ['seen_at' => 'datetime'];
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stateLabel(): string
    {
        return match ($this->state) {
            self::SEEN => 'Vue par l’entreprise',
            self::CONTACTED => 'L’entreprise souhaite échanger',
            self::WITHDRAWN => 'Retirée',
            default => 'Envoyée',
        };
    }

    public function deleteAttachment(): void
    {
        if ($this->attachment_path) {
            Storage::disk('local')->delete($this->attachment_path);
        }
    }
}
