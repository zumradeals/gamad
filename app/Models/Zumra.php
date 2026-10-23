<?php

namespace App\Models;

use App\Enums\ZumraFormat;
use App\Enums\ZumraRole;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ZUMRA : une communauté créée par toute personne autour d'un seul domaine (PRODUIT.md). Toujours écrit en majuscules. Le nom et
 * le domaine ne changent pas après la création (D-114).
 */
class Zumra extends Model
{
    use HasUuids;

    public const NAME_MIN = 3;

    public const NAME_MAX = 80;

    public const MISSION_MAX = 500;

    protected $guarded = ['id', 'name_key', 'search'];

    protected function casts(): array
    {
        return ['format' => ZumraFormat::class];
    }

    protected static function booted(): void
    {
        static::saving(function (Zumra $zumra) {
            $zumra->name_key = Normalizer::key($zumra->name);
            $zumra->search = Normalizer::key($zumra->name.' '.$zumra->mission);
        });
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(ZumraMembership::class);
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(JoinRequest::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /** Rôle de `$user` dans cette ZUMRA, ou null s'il n'en fait pas partie. */
    public function roleOf(?User $user): ?ZumraRole
    {
        if (! $user) {
            return null;
        }

        return $this->memberships()->where('user_id', $user->getKey())->first()?->role;
    }

    /** Une ZUMRA sans personne n'intéresse plus personne : elle est supprimée avec le dernier compte. */
    public function scopeWithoutMembers($query)
    {
        return $query->whereDoesntHave('memberships');
    }
}
