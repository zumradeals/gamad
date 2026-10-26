<?php

namespace App\Models;

use App\Enums\OpportunityKind;
use App\Support\Normalizer;
use App\Support\SkillList;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Opportunité professionnelle publiée par une entreprise vérifiée (emploi, stage, mission, collaboration).
 * Une seule source de données : le catalogue et la page de l'entreprise lisent cette table.
 */
class Opportunity extends Model
{
    use HasUuids;

    public const DRAFT = 'draft';

    public const PUBLISHED = 'published';

    public const CLOSED = 'closed';

    public const ARCHIVED = 'archived';

    public const MAX_SKILLS = 8;

    public const WORK_MODES = ['on_site' => 'Sur site', 'hybrid' => 'Hybride', 'remote' => 'À distance'];

    public const WORKLOADS = ['full_time' => 'Temps plein', 'part_time' => 'Temps partiel', 'occasional' => 'Ponctuel'];

    protected $guarded = ['id', 'company_id', 'author_id', 'reference', 'status', 'published_at', 'closed_at'];

    protected function casts(): array
    {
        return [
            'kind' => OpportunityKind::class,
            'skills' => 'array',
            'deadline' => 'date',
            'published_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Opportunity $opportunity) {
            $opportunity->reference ??= 'OPP-'.now('Africa/Abidjan')->format('Y').'-'.strtoupper(Str::random(6));
        });
        static::saving(function (Opportunity $opportunity) {
            $opportunity->search_text = $opportunity->buildSearchText();
        });
        static::deleting(function (Opportunity $opportunity) {
            foreach ($opportunity->responses as $response) {
                $response->deleteAttachment();
            }
        });
    }

    /** Texte de recherche : intitulé, description, missions, profil, compétences, secteur et entreprise, sans accents ni majuscules. */
    public function buildSearchText(): string
    {
        $company = $this->relationLoaded('company') ? $this->company : ($this->company_id ? Company::query()->find($this->company_id) : null);

        return Normalizer::key(implode(' ', array_filter([
            $this->title, $this->description, $this->missions, $this->profile, $this->experience, $this->qualifications,
            implode(' ', $this->requiredSkills->pluck('name')->all()), $company?->name, $company?->sector,
        ])));
    }

    /** Mots de la saisie, normalisés : chacun doit figurer dans le texte de recherche. */
    public function scopeMatching(Builder $query, string $text): Builder
    {
        $words = array_filter(explode(' ', Normalizer::key(Str::limit($text, 100, ''))), fn ($word) => $word !== '');
        foreach (array_slice($words, 0, 6) as $word) {
            $query->where('opportunities.search_text', 'like', Normalizer::likeContains($word));
        }

        return $query;
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    /**
     * Compétences recherchées : le référentiel commun des capacités (D1), comme les profils et les publications. La colonne
     * `skills` (texte libre d'avant D1) n'est plus lue ; elle est conservée telle quelle.
     */
    public function requiredSkills(): BelongsToMany
    {
        return $this->belongsToMany(Skill::class, 'opportunity_skill')->withPivot('position')->orderByPivot('position');
    }

    /**
     * Remplace les compétences recherchées par ces noms (déjà nettoyés par SkillList) : une capacité qui existe est réutilisée, une
     * nouvelle est créée. Le texte de recherche est remis à jour.
     *
     * @param  array<int, string>  $names
     */
    public function syncSkills(array $names): void
    {
        DB::transaction(function () use ($names) {
            $this->requiredSkills()->sync(collect($names)->mapWithKeys(
                fn (string $name, int $position) => [Skill::findOrCreateByName($name)->id => ['position' => $position]]
            )->all());
            $this->unsetRelation('requiredSkills');
            $this->forceFill(['search_text' => $this->buildSearchText()])->saveQuietly();
        });
    }

    /** Reprise des compétences saisies en texte libre avant D1 : seulement les opportunités qui n'ont encore aucun lien. */
    public static function linkLegacySkills(): int
    {
        $linked = 0;

        static::query()->whereNotNull('skills')->whereDoesntHave('requiredSkills')->each(function (Opportunity $opportunity) use (&$linked) {
            $names = collect($opportunity->skills ?? [])->map(fn ($name) => Normalizer::display((string) $name))
                ->filter(fn (string $name) => SkillList::isValid($name))->unique(fn (string $name) => Normalizer::key($name))->take(self::MAX_SKILLS)->values()->all();

            if ($names !== []) {
                $opportunity->syncSkills($names);
                $linked++;
            }
        });

        return $linked;
    }

    public function responses(): HasMany
    {
        return $this->hasMany(OpportunityResponse::class);
    }

    /** Dernier jour pour répondre, inclus, au fuseau de l'application. */
    public function expired(): bool
    {
        return $this->deadline !== null && $this->deadline->lt(Carbon::today('Africa/Abidjan'));
    }

    /** Ouverte aux réponses : publiée, non clôturée, non expirée. */
    public function isOpen(): bool
    {
        return $this->status === self::PUBLISHED && ! $this->expired();
    }

    /** « Ouverte », « Date limite dépassée », « Clôturée »… : ce qu'on dit au public. */
    public function statusLabel(): string
    {
        return match (true) {
            $this->status === self::DRAFT => 'Brouillon',
            $this->status === self::ARCHIVED => 'Archivée',
            $this->status === self::CLOSED => 'Clôturée',
            $this->expired() => 'Date limite dépassée',
            default => 'Ouverte',
        };
    }

    /** Ouvertes et visibles au catalogue : entreprise vérifiée, publiée, date limite non dépassée. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('opportunities.status', self::PUBLISHED)
            ->where(fn (Builder $q) => $q->whereNull('opportunities.deadline')->orWhere('opportunities.deadline', '>=', Carbon::today('Africa/Abidjan')->toDateString()))
            ->whereHas('company', fn (Builder $company) => $company->publiclyVisible());
    }

    public function firstLine(int $limit = 150): string
    {
        return Str::limit(trim(preg_replace('/\s+/', ' ', (string) $this->description) ?? ''), $limit);
    }

    /** Lignes d'un texte à puces (missions, profil) : une ligne = un point. */
    public static function bullets(?string $text): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/\R/', (string) $text) ?: [])));
    }

    public function workModeLabel(): ?string
    {
        return self::WORK_MODES[$this->work_mode] ?? null;
    }

    public function workloadLabel(): ?string
    {
        return self::WORKLOADS[$this->workload] ?? null;
    }

    /** Peut-on voir cette opportunité ? Publiée ou clôturée : tout le monde (si l'entreprise est visible) ; brouillon et archive : son entreprise seule. */
    public function visibleTo(?User $viewer): bool
    {
        if ($viewer && $this->company->owner_id === $viewer->id) {
            return true;
        }

        return in_array($this->status, [self::PUBLISHED, self::CLOSED], true) && $this->company->verified();
    }
}
