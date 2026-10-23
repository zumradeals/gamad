<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Message d'une conversation privée : texte brut, 1 000 caractères au plus (D-086). Corps vide = message effacé (D-097). */
class Message extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /** Microsecondes, pour l'ordre des messages et la comparaison avec « lu jusqu'à ». */
    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = ['id', 'conversation_id', 'sender_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function isErased(): bool
    {
        return $this->body === null;
    }
}
