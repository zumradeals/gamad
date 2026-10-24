<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class WorkAttestation extends Model
{
    use HasUuids;

    public const STATES = ['pending' => 'Confirmation en attente', 'confirmed' => 'Expérience confirmée', 'declined' => 'Demande déclinée', 'revoked' => 'Référence retirée par GAMAD', 'withdrawn' => 'Consentement retiré'];

    protected $guarded = ['id'];

    public const ROLES = ['client' => 'Client', 'colleague' => 'Collègue', 'witness' => 'Personne présente'];

    protected function casts(): array
    {
        return ['performed_on' => 'date', 'confirmed_at' => 'datetime', 'revision' => 'integer', 'identity_revision' => 'integer'];
    }

    public function publication()
    {
        return $this->belongsTo(Publication::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function attester()
    {
        return $this->belongsTo(User::class, 'attester_id');
    }

    public function scopeVisibleTo($q, ?User $viewer)
    {
        return $q->where('state', 'confirmed')->whereHas('publication', fn ($p) => $p->visibleTo($viewer)->whereNull('hidden_at'))
            ->whereHas('author.profile', fn ($p) => $p->visibleTo($viewer))
            ->whereHas('attester.profile', fn ($p) => $p->visibleTo($viewer))
            ->whereHas('attester.identityVerification', fn ($v) => $v->current()->whereColumn('identity_verifications.revision', 'work_attestations.identity_revision'));
    }
}
