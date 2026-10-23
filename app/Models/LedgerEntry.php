<?php

namespace App\Models;

use App\Enums\LedgerKind;
use App\Exceptions\ImmutableRecordException;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une ligne du registre d'un portefeuille : jamais modifiée, jamais effacée (la base le refuse aussi). */
class LedgerEntry extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected static function booted(): void
    {
        static::updating(fn () => throw ImmutableRecordException::forLedger());
        static::deleting(fn () => throw ImmutableRecordException::forLedger());
    }

    protected function casts(): array
    {
        return ['kind' => LedgerKind::class, 'amount' => 'integer', 'balance_after' => 'integer', 'sequence' => 'integer', 'created_at' => 'datetime'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
