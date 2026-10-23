<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une recharge de portefeuille par le prestataire de paiement : voir App\Services\Payments. */
class Payment extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    /** Le lien de paiement du prestataire expire après 24 heures. */
    public const LIFETIME_HOURS = 24;

    protected function casts(): array
    {
        return ['status' => PaymentStatus::class, 'amount' => 'integer', 'completed_at' => 'datetime'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
}
