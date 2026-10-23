<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** Une affectation du fonds de contribution à un projet, décidée par le Président (Lot 6c, D-168). */
class Allocation extends Model
{
    use HasUuids;

    public const DECIDED = 'decided';

    public const PAID = 'paid';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['amount' => 'integer', 'paid_at' => 'datetime'];
    }

    public function isPaid(): bool
    {
        return $this->status === self::PAID;
    }
}
