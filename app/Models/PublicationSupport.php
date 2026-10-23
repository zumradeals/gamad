<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Soutien : « Je soutiens » sur une avancée de projet. Un engagement réel compté (« 4 personnes soutiennent »), jamais un « j'aime » :
 * sans note, sans classement, sans liste publique des personnes.
 */
class PublicationSupport extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = ['id', 'publication_id', 'user_id'];

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
