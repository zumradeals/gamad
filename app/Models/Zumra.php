<?php

namespace App\Models;

use App\Enums\ZumraDuty;
use App\Enums\ZumraFormat;
use App\Enums\ZumraRole;
use App\Enums\ZumraSphere;
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

    protected $guarded = ['id', 'name_key', 'search', 'recognized_at', 'recognized_by', 'suspended_at', 'suspended_by', 'suspension_reason'];

    protected function casts(): array
    {
        return ['format' => ZumraFormat::class, 'sphere' => ZumraSphere::class, 'recognized_at' => 'datetime', 'suspended_at' => 'datetime'];
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

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ZumraReport::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ZumraPost::class);
    }

    /** Suspendue par la modération (D-215) : retirée du Carrefour et des fils, lisible seulement par ses membres et la modération. */
    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    /** Reconnue officiellement par GAMAD : l'acte du super administrateur, tracé au journal (D-116, Lot 3c). */
    public function isRecognized(): bool
    {
        return $this->recognized_at !== null;
    }

    public function recognizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recognized_by');
    }

    public function joinRequests(): HasMany
    {
        return $this->hasMany(JoinRequest::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Les cinq postes de gouvernance recommandés. `state` : `occupied` (accepté par la personne), `pending` (proposé, pas encore accepté)
     * ou `free` (à pourvoir). Le responsable principal est le fondateur. Simple lecture : aucun score ni privilège n'en découle.
     *
     * @return array<int, array{label: string, holder: ?User, duty: ?ZumraDuty, state: string}>
     */
    public function governance(): array
    {
        $memberships = $this->memberships()->with('user')->whereHas('user', fn ($user) => $user->active())->whereIn('role', [ZumraRole::Founder->value, ZumraRole::Manager->value])->get();

        $founder = $memberships->firstWhere('role', ZumraRole::Founder)?->user;
        $posts = [['label' => 'Responsable principal', 'holder' => $founder, 'duty' => null, 'state' => $founder ? 'occupied' : 'free']];

        foreach (ZumraDuty::cases() as $duty) {
            $membership = $memberships->first(fn ($m) => $m->role === ZumraRole::Manager && $m->duty === $duty);
            $posts[] = [
                'label' => $duty->label(),
                'holder' => $membership?->user,
                'duty' => $duty,
                'state' => $membership === null ? 'free' : ($membership->dutyPending() ? 'pending' : 'occupied'),
            ];
        }

        return $posts;
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
