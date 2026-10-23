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
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** Visible des seuls membres de la ZUMRA au nom de laquelle elle est publiée (D-112). */
    public const ZUMRA = 'zumra';

    protected $guarded = ['id', 'author_id'];

    protected function casts(): array
    {
        return [
            'type' => PublicationType::class,
            'need_state' => NeedState::class,
            'resolved_at' => 'datetime',
            'hidden_at' => 'datetime',
            'explained_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** La personne de la modération qui a masqué la publication (vide si son compte n'existe plus). */
    public function hider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hidden_by');
    }

    /** La ZUMRA au nom de laquelle la publication est faite (vide pour une publication personnelle). */
    public function zumra(): BelongsTo
    {
        return $this->belongsTo(Zumra::class);
    }

    /** Le projet au nom duquel la publication est faite (avancée ou réalisation ; vide sinon). */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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

    public function gratitude(): HasOne
    {
        return $this->hasOne(Gratitude::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class);
    }

    /** Les soutiens reçus (avancée de projet) : un engagement compté, jamais une approbation. */
    public function supports(): HasMany
    {
        return $this->hasMany(PublicationSupport::class);
    }

    public function gestures(): HasMany
    {
        return $this->hasMany(Gesture::class);
    }

    /**
     * Publications que `$viewer` a le droit de voir (D-085, D-112, règle côté serveur) : celles d'une personne dont l'adresse
     * e-mail est vérifiée, publiques pour tout le monde, « inscrits seulement » pour une personne connectée et vérifiée,
     * « ZUMRA seulement » pour les membres de cette ZUMRA ; chacun voit toujours les siennes, sauf celles de niveau ZUMRA qu'il
     * ne pourrait plus lire (il a quitté la ZUMRA). Une publication masquée n'est vue que par ceux qui la gèrent et la
     * modération. Même règle que App\Policies\PublicationPolicy::view().
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): Builder
    {
        // Une publication masquée n'existe plus que pour ceux qui la gèrent et la modération (D-089).
        if (! $viewer?->can('moderate')) {
            $managed = $viewer ? $viewer->managedZumraIds() : [];

            $query->where(function (Builder $query) use ($viewer, $managed) {
                $query->whereNull('hidden_at');

                if ($viewer) {
                    $query->orWhere('author_id', $viewer->getKey());
                }

                if ($managed !== []) {
                    $query->orWhereIn('zumra_id', $managed);
                }
            });
        }

        return $query->where(function (Builder $query) use ($viewer) {
            $query->where(function (Builder $query) use ($viewer) {
                $query->whereHas('author', fn (Builder $author) => $author->whereNotNull('email_verified_at'));

                $query->where(function (Builder $level) use ($viewer) {
                    $level->where('visibility', self::PUBLIC);

                    if ($viewer?->hasVerifiedEmail()) {
                        $level->orWhere('visibility', self::MEMBERS)
                            ->orWhere(fn (Builder $zumra) => $zumra->where('visibility', self::ZUMRA)
                                ->whereIn('zumra_id', $viewer->zumraMemberships()->select('zumra_id')));
                    }
                });
            });

            // Chacun voit les siennes, sauf celles de niveau ZUMRA, qui ne se lisent que par un membre.
            if ($viewer) {
                $query->orWhere(fn (Builder $own) => $own->where('author_id', $viewer->getKey())->where('visibility', '!=', self::ZUMRA));
            }
        });
    }

    /** Publications que `$user` gère : les siennes, et celles des ZUMRA dont il est fondateur ou responsable. */
    public function scopeManagedBy(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user) {
            $query->where('author_id', $user->getKey());

            $managed = $user->managedZumraIds();

            if ($managed !== []) {
                $query->orWhereIn('zumra_id', $managed);
            }
        });
    }

    /**
     * Cette personne gère-t-elle la publication ? Son auteur, ou, pour une publication faite au nom d'une ZUMRA, le fondateur
     * et les responsables de cette ZUMRA (D-116). `$managedZumraIds` évite de relire la base pour chaque carte d'une liste.
     *
     * @param  array<int, string>|null  $managedZumraIds
     */
    public function isManagedBy(?User $user, ?array $managedZumraIds = null): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->getKey() === $this->author_id) {
            return true;
        }

        return $this->zumra_id !== null && in_array($this->zumra_id, $managedZumraIds ?? $user->managedZumraIds(), true);
    }

    /** Masquée par la modération (D-089) : seuls ceux qui la gèrent et la modération la voient encore. */
    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }

    /** Personnes à prévenir quand quelqu'un répond : l'auteur, ou le fondateur et les responsables de la ZUMRA. */
    public function managers()
    {
        if ($this->zumra_id === null) {
            return collect([$this->author]);
        }

        return User::query()->whereIn('id', ZumraMembership::query()->where('zumra_id', $this->zumra_id)->whereIn('role', ['founder', 'manager'])->select('user_id'))->get();
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
        // Une réponse à un Appel à rejoindre est une demande d'adhésion, jamais un geste (D-116) ; une publication de projet n'en
        // reçoit pas encore (PR 4b).
        if ($this->isHidden() || ! $this->type->takesGestures()) {
            return false;
        }

        return ! $this->isNeed() || ($this->need_state?->isActive() ?? false);
    }

    /** Gestes encore « vivants » : envoyés ou acceptés. Sert au fait d'engagement affiché (« 2 personnes proposent leur aide »). */
    public function liveGesturesCount(): int
    {
        return $this->gestures()->whereIn('state', [GestureState::Sent->value, GestureState::Accepted->value])->count();
    }
}
