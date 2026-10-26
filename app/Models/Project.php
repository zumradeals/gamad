<?php

namespace App\Models;

use App\Enums\ProjectHostingState;
use App\Enums\ProjectStage;
use App\Enums\ProjectState;
use App\Support\Normalizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Projet : une initiative portée par une personne, accueillie par une ZUMRA qui doit l'accepter (P1, D-206). Il est privé par défaut :
 * seuls son porteur et ses collaborateurs l'ouvrent. Il peut publier une présentation publique limitée, composée des seules
 * informations que son porteur a choisies. L'accueil ne transfère ni la propriété, ni la direction, ni les ressources.
 */
class Project extends Model
{
    use HasUuids;

    public const NAME_MIN = 3;

    public const NAME_MAX = 80;

    public const GOAL_MAX = 500;

    public const SUMMARY_MAX = 300;

    public const CONTRIBUTE_MAX = 500;

    public const MAX_SKILLS = 8;

    protected $guarded = ['id', 'zumra_id', 'owner_id', 'name_key', 'search', 'state', 'state_changed_at', 'stage', 'hosting_state', 'hosting_message', 'hosting_requested_at', 'hosting_decided_at', 'hosting_decided_by', 'origin_zumra_id', 'showcase_published_at', 'showcase_summary', 'showcase_contribute', 'showcase_show_owner', 'showcase_show_needs'];

    protected function casts(): array
    {
        return [
            'state' => ProjectState::class, 'state_changed_at' => 'datetime', 'stage' => ProjectStage::class,
            'hosting_state' => ProjectHostingState::class, 'hosting_requested_at' => 'datetime', 'hosting_decided_at' => 'datetime',
            'showcase_published_at' => 'datetime', 'showcase_show_owner' => 'boolean', 'showcase_show_needs' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Project $project) {
            $project->name_key = Normalizer::key($project->name);
            $project->search = Normalizer::key($project->name.' '.$project->goal);
        });
    }

    public function zumra(): BelongsTo
    {
        return $this->belongsTo(Zumra::class);
    }

    /** Le porteur : la personne qui dirige le projet. Vide seulement si son compte n'existe plus. */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    /** La première ZUMRA qui a accueilli le projet : le lien historique, conservé. */
    public function originZumra(): BelongsTo
    {
        return $this->belongsTo(Zumra::class, 'origin_zumra_id');
    }

    /** Compétences recherchées : le référentiel commun des capacités, comme les profils, les publications et les opportunités. */
    public function neededSkills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'project_skill')->withPivot('position')->orderByPivot('position');
    }

    /** @param  array<int, string>  $names déjà nettoyés par SkillList */
    public function syncSkills(array $names): void
    {
        DB::transaction(function () use ($names) {
            $this->neededSkills()->sync(collect($names)->take(self::MAX_SKILLS)->values()
                ->mapWithKeys(fn (string $name, int $position) => [Skill::findOrCreateByName($name)->id => ['position' => $position]])->all());
            $this->unsetRelation('neededSkills');
        });
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(ProjectInvitation::class);
    }

    public function companyLinks(): HasMany
    {
        return $this->hasMany(ProjectCompanyLink::class);
    }

    /** Domaine du projet, à défaut celui de sa ZUMRA (projets d'avant P1). */
    public function domainLabel(): ?string
    {
        return ($this->domain ?? $this->zumra?->domain)?->name;
    }

    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && $this->owner_id !== null && $this->owner_id === $user->getKey();
    }

    public function isCollaborator(?User $user): bool
    {
        return $user !== null && $this->members()->where('user_id', $user->getKey())->exists();
    }

    /** Accueilli par sa ZUMRA : seul un accueil accepté confirme le rattachement. */
    public function isHosted(): bool
    {
        return $this->hosting_state === ProjectHostingState::Accepted;
    }

    public function isShowcasePublished(): bool
    {
        return $this->showcase_published_at !== null && $this->isHosted();
    }

    /**
     * Confidentialité vue du porteur : « Projet privé » (lui seul) ou « Projet partagé » (avec des collaborateurs qui ont accepté). La
     * présentation publique est un choix distinct, et limité.
     */
    public function privacyLabel(): string
    {
        return $this->members()->exists() ? 'Projet partagé' : 'Projet privé';
    }

    /** Les projets dont une personne ouvre le dossier : celui qu'elle porte, ceux où elle collabore. Jamais par sa seule appartenance à une ZUMRA. */
    public function scopeOpenableBy(Builder $query, ?User $user): Builder
    {
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(fn (Builder $q) => $q->where('projects.owner_id', $user->getKey())
            ->orWhereHas('members', fn (Builder $members) => $members->where('user_id', $user->getKey())));
    }

    /** Les présentations publiques d'une ZUMRA : projets accueillis et publiés par leur porteur. */
    public function scopeShowcased(Builder $query): Builder
    {
        return $query->whereNotNull('showcase_published_at')->where('hosting_state', ProjectHostingState::Accepted->value);
    }

    /** Les étapes, dans l'ordre. */
    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class)->orderBy('position');
    }

    /** L'équipe : les membres de la ZUMRA qui participent au projet. */
    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function participationRequests(): HasMany
    {
        return $this->hasMany(ParticipationRequest::class);
    }

    /** Les publications faites au nom du projet : avancées, réalisations et besoins. */
    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function stateChanges(): HasMany
    {
        return $this->hasMany(ProjectStateChange::class);
    }
}
