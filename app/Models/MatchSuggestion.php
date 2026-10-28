<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Reçu privé de découverte : aucune copie de contenu ni de raison devenue périmée. */
class MatchSuggestion extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['ignored_at' => 'datetime', 'alerted_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function opportunity()
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function offer()
    {
        return $this->belongsTo(Publication::class, 'offer_id');
    }
}
