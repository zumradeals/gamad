<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Company extends Model
{
    use HasUuids;

    public const VERIFIED = 'verified';

    protected $guarded = ['id', 'owner_id', 'status', 'reviewer_id', 'verified_at', 'revision'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'revision' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(function (Company $company) {
            foreach ($company->documents as $document) {
                Storage::disk('local')->delete($document->private_path);
            }
            Storage::disk('local')->delete(array_filter([$company->logo_path, $company->banner_path]));
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function documents()
    {
        return $this->hasMany(CompanyDocument::class);
    }

    public function verified(): bool
    {
        return $this->status === self::VERIFIED && $this->verified_at !== null;
    }

    public function scopePubliclyVisible($query)
    {
        return $query->where('status', self::VERIFIED)->whereNotNull('verified_at');
    }
}
