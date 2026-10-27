<?php

namespace App\Models;

use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reversement du Zahab Wallet (D-218) : GAMAD reverse tout ou partie du solde au titulaire indiqué, par Mobile Money. Le numéro est
 * chiffré en base ; il n'est jamais montré à un autre que la personne qui l'a indiqué et le super administrateur qui exécute le versement.
 */
class Payout extends Model
{
    use HasUuids;

    public const OPERATORS = ['wave' => 'Wave', 'orange' => 'Orange Money', 'mtn' => 'MTN Money', 'moov' => 'Moov Money', 'autre' => 'Autre opérateur'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => PayoutStatus::class, 'phone' => 'encrypted', 'handled_at' => 'datetime'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function isOpen(): bool
    {
        return $this->status === PayoutStatus::Requested;
    }

    /** Numéro masqué pour les listes : « +225 ••• ••• 78 ». */
    public function maskedPhone(): string
    {
        $digits = (string) $this->phone;

        return strlen($digits) <= 4 ? '••••' : substr($digits, 0, 3).' •••• '.substr($digits, -2);
    }
}
