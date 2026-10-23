<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Une contribution au fonds de contribution (Lot 6c, D-163, D-165) : voir App\Services\Contributions. */
class Contribution extends Model
{
    use HasUuids;

    public const AWAITING = 'awaiting_validation';

    public const RECORDED = 'recorded';

    public const REFUSED = 'refused';

    public const REFUNDED = 'refunded';

    public const WITHDRAWN = 'withdrawn';

    public const FUND = 'fund';

    public const ZUMRA = 'zumra';

    public const PROJECT = 'project';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'decided_at' => 'datetime'];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function isRecorded(): bool
    {
        return $this->status === self::RECORDED;
    }

    public function isAwaiting(): bool
    {
        return $this->status === self::AWAITING;
    }
}
