<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Une seule demande courante par paire ; le lien ne révèle aucun compteur public. */
class PersonConnection extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    public function otherId(User $user): string
    {
        return $this->first_id === $user->id ? $this->second_id : $this->first_id;
    }

    public function scopeForPerson($query, User $user)
    {
        return $query->where(fn ($q) => $q->where('first_id', $user->id)->orWhere('second_id', $user->id));
    }
}
