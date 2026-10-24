<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class IdentityVerification extends Model
{
    use HasUuids;

    public const STATES = ['pending' => 'Contrôle en attente', 'verified' => 'Identité vérifiée', 'declined' => 'Demande refusée', 'revoked' => 'Vérification retirée', 'withdrawn' => 'Consentement retiré'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'expires_at' => 'datetime', 'revision' => 'integer'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopeCurrent($q)
    {
        return $q->where('state', 'verified')->where('expires_at', '>', now())->whereHas('user', fn ($u) => $u->active()->whereColumn('users.name', 'identity_verifications.checked_name'));
    }
}
