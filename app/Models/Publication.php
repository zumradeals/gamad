<?php

namespace App\Models;

use App\Enums\GestureState;
use App\Enums\NeedState;
use App\Enums\PublicationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Publication d'une personne dans le Fil (D-081). Un besoin est une publication de type « J'ai besoin » qui porte un état.
 * Texte brut : il n'est jamais interprété (ni lien automatique, ni HTML, ni image).
 */
class Publication extends Model
{
    use HasUuids;

    public const BODY_MAX = 500;

    public const MAX_SKILLS = 3;

    public const PUBLIC = 'public';

    public const MEMBERS = 'members';

    protected $guarded = ['id', 'author_id'];

    protected function casts(): array
    {
        return [
            'type' => PublicationType::class,
            'need_state' => NeedState::class,
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function skills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'publication_skill')->withPivot('position')->orderByPivot('position');
    }

    public function gestures(): HasMany
    {
        return $this->hasMany(Gesture::class);
    }

    /**
     * Publications que `$viewer` a le droit de voir (D-085, règle côté serveur) : celles d'une personne dont l'adresse e-mail
     * est vérifiée, publiques pour tout le monde, « inscrits seulement » pour une personne connectée et vérifiée ; chacun voit
     * toujours les siennes. Même règle que App\Policies\PublicationPolicy::view().
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        return $query->where(function (Builder $query) use ($viewer) {
            $query->where(function (Builder $query) use ($viewer) {
                $query->whereHas('author', fn (Builder $author) => $author->whereNotNull('email_verified_at'));

                if (! $viewer?->hasVerifiedEmail()) {
                    $query->where('visibility', self::PUBLIC);
                }
            });

            if ($viewer) {
                $query->orWhere('author_id', $viewer->getKey());
            }
        });
    }

    public function isNeed(): bool
    {
        return $this->type === PublicationType::Need;
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::PUBLIC;
    }

    /** Un besoin résolu ou fermé ne reçoit plus de geste ; les autres types restent toujours ouverts. */
    public function acceptsGestures(): bool
    {
        return ! $this->isNeed() || ($this->need_state?->isActive() ?? false);
    }

    /** Gestes encore « vivants » : envoyés ou acceptés. Sert au fait d'engagement affiché (« 2 personnes proposent leur aide »). */
    public function liveGesturesCount(): int
    {
        return $this->gestures()->whereIn('state', [GestureState::Sent->value, GestureState::Accepted->value])->count();
    }
}
