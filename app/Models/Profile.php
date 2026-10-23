<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Profil d'une personne : ce qu'elle apporte, ce qu'elle veut apprendre, où elle est (D-065 à D-070).
 * L'e-mail et le téléphone n'en font jamais partie.
 */
class Profile extends Model
{
    use HasUuids;

    public const BIO_MAX = 280;

    public const MAX_SKILLS_PER_KIND = 10;

    public const MAX_DOMAINS = 3;

    public const PUBLIC = 'public';

    public const MEMBERS = 'members';

    public const PHOTO_DISK = 'local';

    protected $guarded = ['id', 'user_id', 'public_id'];

    protected static function booted(): void
    {
        static::creating(function (Profile $profile) {
            do {
                $profile->public_id = Str::lower(Str::random(12));
            } while (static::query()->where('public_id', $profile->public_id)->exists());
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** Je peux apporter (`offered_skills`). */
    public function offeredSkills(): BelongsToMany
    {
        return $this->skillsOfKind('offered');
    }

    /** Je veux apprendre (`wanted_skills`). */
    public function wantedSkills(): BelongsToMany
    {
        return $this->skillsOfKind('wanted');
    }

    public function domains(): BelongsToMany
    {
        return $this->belongsToMany(Domain::class, 'domain_profile')->orderBy('domains.sort_order')->orderBy('domains.name');
    }

    private function skillsOfKind(string $kind): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'profile_skill')
            ->wherePivot('kind', $kind)
            ->withPivot('position')
            ->orderByPivot('position');
    }

    /**
     * Profils que `$viewer` a le droit de voir (D-067) : ceux d'une adresse e-mail vérifiée, publics pour tout le monde,
     * « inscrits seulement » pour une personne connectée et vérifiée ; chacun voit toujours le sien.
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        return $query->where(function (Builder $query) use ($viewer) {
            $query->where(function (Builder $query) use ($viewer) {
                $query->whereHas('user', fn (Builder $user) => $user->active());

                if (! $viewer?->hasVerifiedEmail()) {
                    $query->where('visibility', self::PUBLIC);
                }
            });

            if ($viewer) {
                $query->orWhere('user_id', $viewer->getKey());
            }
        });
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::PUBLIC;
    }

    public function hasPhoto(): bool
    {
        return filled($this->photo_path);
    }

    public function photoUrl(): ?string
    {
        return $this->hasPhoto()
            ? route('personnes.photo', ['profile' => $this->public_id, 'v' => substr(md5($this->photo_path), 0, 8)])
            : null;
    }

    public function deletePhotoFile(): void
    {
        if ($this->hasPhoto()) {
            Storage::disk(self::PHOTO_DISK)->delete($this->photo_path);
        }
    }

    /** Une capacité au moins : c'est ce qui fait apparaître la personne dans le Carrefour Personnes. */
    public function hasSkills(): bool
    {
        return $this->offeredSkills()->exists() || $this->wantedSkills()->exists();
    }
}
