<?php

namespace App\Models;

use App\Enums\BeneficiaryKind;
use App\Enums\HelpKind;
use App\Enums\ProposalState;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Proposition de contribution (C1, D-207) : un engagement volontaire d'une personne envers un bénéficiaire (GAMAD, une ZUMRA, un
 * projet, une personne ou une entreprise), spontané ou en réponse à un besoin. Ni paiement, ni droit de propriété, de direction ou
 * de contrôle : une intention financière n'est ni payée ni encaissée. Les mots de la personne ne deviennent jamais publics seuls.
 */
class ContributionProposal extends Model
{
    use HasUuids;

    public const OFFER_MAX = 500;

    public const MESSAGE_MAX = 500;

    public const NOTE_MAX = 280;

    public const PRIVATE = 'private';

    public const PUBLIC = 'public';

    protected $guarded = ['id', 'user_id', 'state', 'decided_by', 'decided_at', 'declared_at', 'realized_at', 'realized_by'];

    protected function casts(): array
    {
        return [
            'kind' => HelpKind::class,
            'state' => ProposalState::class,
            'official' => 'boolean',
            'decided_at' => 'datetime',
            'declared_at' => 'datetime',
            'realized_at' => 'datetime',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function need(): BelongsTo
    {
        return $this->belongsTo(Publication::class, 'need_id');
    }

    public function zumra(): BelongsTo
    {
        return $this->belongsTo(Zumra::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function person(): BelongsTo
    {
        return $this->belongsTo(User::class, 'person_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function conversation(): HasOne
    {
        return $this->hasOne(Conversation::class);
    }

    public function beneficiaryKind(): BeneficiaryKind
    {
        return match (true) {
            $this->official => BeneficiaryKind::Gamad,
            $this->zumra_id !== null => BeneficiaryKind::Zumra,
            $this->project_id !== null => BeneficiaryKind::Project,
            $this->company_id !== null => BeneficiaryKind::Company,
            default => BeneficiaryKind::Person,
        };
    }

    /** Le nom du bénéficiaire, tel qu'on peut l'écrire à toute personne qui voit la proposition. */
    public function beneficiaryName(): string
    {
        return match ($this->beneficiaryKind()) {
            BeneficiaryKind::Gamad => 'GAMAD',
            BeneficiaryKind::Zumra => $this->zumra?->name ?? 'ZUMRA',
            BeneficiaryKind::Project => $this->project?->name ?? 'Projet',
            BeneficiaryKind::Company => $this->company?->name ?? 'Entreprise',
            BeneficiaryKind::Person => $this->person?->name ?? 'Personne supprimée',
        };
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::PUBLIC;
    }

    public function isOpen(): bool
    {
        return $this->state->isOpen();
    }

    /** Propositions publiques que l'on peut afficher : jamais une proposition retirée ou refusée. */
    public function scopeListable(Builder $query): Builder
    {
        return $query->where('visibility', self::PUBLIC)
            ->whereIn('state', [ProposalState::Proposed->value, ProposalState::Accepted->value, ProposalState::Declared->value, ProposalState::Realized->value]);
    }
}
