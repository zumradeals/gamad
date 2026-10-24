<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class TrustReport extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    public const CATEGORIES = ['identity' => 'Identité trompeuse', 'reference' => 'Référence contestée', 'abuse' => 'Autre abus', 'appeal' => 'Recours contre une décision'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime', 'revision' => 'integer'];
    }

    public function scopeForReview($query, User $viewer)
    {
        return $query->where('reporter_id', '!=', $viewer->id)->where('subject_id', '!=', $viewer->id)
            ->where(fn ($q) => $q->whereNull('work_attestation_id')->orWhereHas('workAttestation', fn ($w) => $w->where('attester_id', '!=', $viewer->id)));
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function subject()
    {
        return $this->belongsTo(User::class, 'subject_id');
    }

    public function workAttestation()
    {
        return $this->belongsTo(WorkAttestation::class);
    }
}
