<?php

namespace App\Services;

use App\Enums\BeneficiaryKind;
use App\Enums\HelpKind;
use App\Enums\NeedState;
use App\Enums\PublicationType;
use App\Models\Company;
use App\Models\ContributionProposal;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Publication;
use App\Models\User;
use App\Models\Zumra;
use App\Support\Normalizer;
use App\Support\ZumraModule;
use Illuminate\Database\Eloquent\Builder;

/**
 * Les requêtes du module Contributions (C1, D-207) : besoins et propositions que cette personne a le droit de voir, du plus récent au plus
 * ancien (jamais de classement). Les mêmes règles de visibilité que le Fil, plus celles des modules : une ZUMRA ou un projet fermés
 * (module ZUMRA) ou privés (P1) n'apparaissent jamais ici ; une proposition privée n'apparaît que pour ceux qui la voient.
 */
class ContributionFeed
{
    /**
     * @param  array{q?: string, kinds?: array<int, string>, beneficiaries?: array<int, string>, domain?: string, city?: string}  $filters
     */
    public function needs(?User $viewer, array $filters = []): Builder
    {
        $query = Publication::query()
            ->visibleTo($viewer)
            ->whereNull('hidden_at')
            ->where('type', PublicationType::Need->value)
            // Une opportunité professionnelle d'entreprise (D-194) n'est pas une demande d'entraide : elle a son propre catalogue.
            ->where(fn (Builder $q) => $q->whereNull('company_id')->orWhereNotNull('help_kind'))
            ->whereIn('need_state', [NeedState::Open->value, NeedState::InProgress->value, NeedState::Resolved->value]);

        if (! ZumraModule::accessibleTo($viewer)) {
            $query->whereNull('zumra_id');
        } else {
            // Un besoin de projet n'existe que si la présentation publique du projet est publiée (P1).
            $query->where(fn (Builder $q) => $q->whereNull('project_id')->orWhereIn('project_id', Project::query()->showcased()->select('id')));
        }

        $this->applyNeedFilters($query, $filters);

        return $query->with(['author.profile', 'domain', 'city', 'zumra', 'project', 'company'])->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * Propositions publiques que cette personne peut voir : jamais une proposition privée, retirée ou refusée, ni celle d'un bénéficiaire
     * qui n'existe pas pour elle.
     *
     * @param  array{q?: string, kinds?: array<int, string>, beneficiaries?: array<int, string>, domain?: string, city?: string}  $filters
     */
    public function proposals(?User $viewer, array $filters = []): Builder
    {
        $query = ContributionProposal::query()->listable()
            ->whereHas('author', fn (Builder $author) => $author->active());

        if ($viewer && ! $viewer->can('moderate')) {
            $query->whereNotIn('user_id', People::excludedIds($viewer));
        }

        $zumraOpen = ZumraModule::accessibleTo($viewer);
        $query->where(function (Builder $q) use ($zumraOpen) {
            $q->where('official', true)
                ->orWhere(fn (Builder $c) => $c->whereNotNull('company_id')->whereIn('company_id', Company::query()->publiclyVisible()->select('id')))
                ->orWhere(fn (Builder $p) => $p->whereNotNull('person_id')->whereNotNull('need_id'));

            if ($zumraOpen) {
                $q->orWhereNotNull('zumra_id')
                    ->orWhere(fn (Builder $p) => $p->whereNotNull('project_id')->whereIn('project_id', Project::query()->showcased()->select('id')));
            }
        });

        // Une réponse à un besoin ne se voit que si le besoin lui-même est visible.
        $query->where(fn (Builder $q) => $q->whereNull('need_id')->orWhereIn('need_id', $this->needs($viewer)->select('publications.id')->reorder()));

        $this->applyProposalFilters($query, $filters);

        return $query->with(['author.profile', 'zumra', 'project', 'company', 'person', 'domain', 'city', 'need'])->orderByDesc('created_at')->orderByDesc('id');
    }

    /** Propositions que cette personne reçoit au nom d'un bénéficiaire qu'elle représente (elle-même, sa ZUMRA, son projet, son entreprise, GAMAD). */
    public function handledBy(User $user): Builder
    {
        $zumras = ZumraModule::accessibleTo($user) ? $user->managedZumraIds() : [];
        $projects = ZumraModule::accessibleTo($user)
            ? Project::query()->where('owner_id', $user->getKey())->pluck('id')
                ->merge(ProjectMember::query()->where('user_id', $user->getKey())->where('manages_contributions', true)->pluck('project_id'))->unique()->all()
            : [];
        $companies = Company::query()->publiclyVisible()->where('owner_id', $user->getKey())->pluck('id')->all();

        return ContributionProposal::query()->where(function (Builder $q) use ($user, $zumras, $projects, $companies) {
            $q->where('person_id', $user->getKey())
                ->orWhereIn('zumra_id', $zumras)
                ->orWhereIn('project_id', $projects)
                ->orWhereIn('company_id', $companies);

            if ($user->isSuperAdmin()) {
                $q->orWhere('official', true);
            }
        });
    }

    /** Besoins que cette personne gère : ceux qu'elle a publiés, ceux de ses ZUMRA, projets et entreprises, et ceux de GAMAD pour le super administrateur. */
    public function managedNeeds(User $user): Builder
    {
        $zumras = ZumraModule::accessibleTo($user) ? $user->managedZumraIds() : [];

        return Publication::query()->where('type', PublicationType::Need->value)->whereNotNull('help_kind')->where(function (Builder $q) use ($user, $zumras) {
            $q->where(fn (Builder $own) => $own->where('author_id', $user->getKey())->where('official', false)->whereNull('project_id'))
                ->orWhere(fn (Builder $zumra) => $zumra->whereIn('zumra_id', $zumras)->whereNull('project_id'));

            $projects = Project::query()->where('owner_id', $user->getKey())->pluck('id')
                ->merge(ProjectMember::query()->where('user_id', $user->getKey())->where('manages_contributions', true)->pluck('project_id'))->unique()->all();

            if ($projects !== [] && ZumraModule::accessibleTo($user)) {
                $q->orWhereIn('project_id', $projects);
            }

            if ($user->isSuperAdmin()) {
                $q->orWhere('official', true);
            }
        })->orderByDesc('created_at')->orderByDesc('id');
    }

    /** @param  array<string, mixed>  $filters */
    private function applyNeedFilters(Builder $query, array $filters): void
    {
        $key = Normalizer::key((string) ($filters['q'] ?? ''));
        $kinds = array_values(array_intersect($filters['kinds'] ?? [], array_column(HelpKind::cases(), 'value')));
        $beneficiaries = array_values(array_intersect($filters['beneficiaries'] ?? [], array_column(BeneficiaryKind::cases(), 'value')));

        $query
            ->when($key !== '', fn (Builder $q) => $q->where(fn (Builder $text) => $text
                ->whereRaw(Normalizer::sqlKey('publications.body').' like ?', [Normalizer::likeContains($key)])
                ->orWhereRaw(Normalizer::sqlKey('publications.title').' like ?', [Normalizer::likeContains($key)])))
            ->when($kinds !== [], fn (Builder $q) => $q->whereIn('help_kind', $kinds))
            ->when($beneficiaries !== [], fn (Builder $q) => $q->where(function (Builder $any) use ($beneficiaries) {
                foreach ($beneficiaries as $beneficiary) {
                    match (BeneficiaryKind::from($beneficiary)) {
                        BeneficiaryKind::Gamad => $any->orWhere('official', true),
                        BeneficiaryKind::Project => $any->orWhereNotNull('project_id'),
                        BeneficiaryKind::Company => $any->orWhereNotNull('company_id'),
                        BeneficiaryKind::Zumra => $any->orWhere(fn (Builder $z) => $z->whereNotNull('zumra_id')->whereNull('project_id')),
                        BeneficiaryKind::Person => $any->orWhere(fn (Builder $p) => $p->where('official', false)->whereNull('zumra_id')->whereNull('project_id')->whereNull('company_id')),
                    };
                }
            }))
            ->when(filled($filters['domain'] ?? null), fn (Builder $q) => $q->where('domain_id', $filters['domain']))
            ->when(filled($filters['city'] ?? null), fn (Builder $q) => $q->where('city_id', $filters['city']));
    }

    /** @param  array<string, mixed>  $filters */
    private function applyProposalFilters(Builder $query, array $filters): void
    {
        $key = Normalizer::key((string) ($filters['q'] ?? ''));
        $kinds = array_values(array_intersect($filters['kinds'] ?? [], array_column(HelpKind::cases(), 'value')));
        $beneficiaries = array_values(array_intersect($filters['beneficiaries'] ?? [], array_column(BeneficiaryKind::cases(), 'value')));

        $query
            ->when($key !== '', fn (Builder $q) => $q->whereRaw(Normalizer::sqlKey('contribution_proposals.offer').' like ?', [Normalizer::likeContains($key)]))
            ->when($kinds !== [], fn (Builder $q) => $q->whereIn('kind', $kinds))
            ->when($beneficiaries !== [], fn (Builder $q) => $q->where(function (Builder $any) use ($beneficiaries) {
                foreach ($beneficiaries as $beneficiary) {
                    match (BeneficiaryKind::from($beneficiary)) {
                        BeneficiaryKind::Gamad => $any->orWhere('official', true),
                        BeneficiaryKind::Project => $any->orWhereNotNull('project_id'),
                        BeneficiaryKind::Company => $any->orWhereNotNull('company_id'),
                        BeneficiaryKind::Zumra => $any->orWhereNotNull('zumra_id'),
                        BeneficiaryKind::Person => $any->orWhereNotNull('person_id'),
                    };
                }
            }))
            ->when(filled($filters['domain'] ?? null), fn (Builder $q) => $q->where('domain_id', $filters['domain']))
            ->when(filled($filters['city'] ?? null), fn (Builder $q) => $q->where('city_id', $filters['city']));
    }
}
