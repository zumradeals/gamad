<?php

namespace App\Core\Identity;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SecurityAccessEvent extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $dateFormat = 'Y-m-d H:i:s.u';

    protected $guarded = [];

    protected $hidden = ['ip', 'user_agent', 'source_key', 'target_key'];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime', 'ip' => 'encrypted', 'user_agent' => 'encrypted', 'sensitive_target' => 'boolean'];
    }
}
