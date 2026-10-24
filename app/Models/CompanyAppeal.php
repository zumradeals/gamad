<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class CompanyAppeal extends Model
{
    use HasUuids;

    protected $guarded = ['id', 'status', 'reviewer_id', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requester_id');
    }
}
