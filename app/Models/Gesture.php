<?php

namespace App\Models;

use App\Enums\GestureState;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Geste d'action : la réponse d'une personne à une publication (D-082). Une personne n'a qu'un geste par publication.
 * Le mot qui l'accompagne est un texte brut de 280 caractères au plus.
 */
class Gesture extends Model
{
    use HasUuids;

    public const MESSAGE_MAX = 280;

    protected $guarded = ['id', 'publication_id', 'user_id'];

    protected function casts(): array
    {
        return [
            'state' => GestureState::class,
            'decided_at' => 'datetime',
        ];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    /** La conversation privée ouverte par ce geste, une fois accepté (D-097 : elle survit à la suppression d'un compte). */
    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
