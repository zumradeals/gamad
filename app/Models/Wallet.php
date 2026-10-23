<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Portefeuille ZAHAB d'une personne ou d'une ZUMRA (D-164). Son solde n'est jamais stocké ici : c'est celui du dernier mouvement de
 * son registre, qui est en ajout seul.
 */
class Wallet extends Model
{
    use HasUuids;

    public const PERSON = 'person';

    public const ZUMRA = 'zumra';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['closed_at' => 'datetime'];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->orderByDesc('sequence');
    }

    public function isClosed(): bool
    {
        return $this->closed_at !== null;
    }

    public function isPerson(): bool
    {
        return $this->owner_type === self::PERSON;
    }
}
