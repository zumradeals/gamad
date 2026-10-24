<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Un enregistrement privé ne constitue ni appréciation, ni relation, ni statistique publique. */
class PublicationBookmark extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['user_id', 'publication_id'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function publication(): BelongsTo
    {
        return $this->belongsTo(Publication::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
