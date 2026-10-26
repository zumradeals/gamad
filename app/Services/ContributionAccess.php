<?php

namespace App\Services;

use App\Enums\BeneficiaryKind;
use App\Models\Company;
use App\Models\ContributionProposal;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Publication;
use App\Models\User;
use App\Models\Zumra;
use App\Support\ContributionsModule;
use App\Support\ZumraModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Qui représente un bénéficiaire de contribution (C1, D-207), et qui peut le connaître. Une seule source pour tous les écrans et tous
 * les services, côté serveur :
 *  - une personne : elle-même ;
 *  - une ZUMRA : son fondateur et ses responsables (module ZUMRA ouvert) ;
 *  - un projet : son porteur, ou la personne qu'il a explicitement habilitée ; l'accueil par une ZUMRA ne donne aucun pouvoir ;
 *  - une entreprise : son gestionnaire, si elle est administrativement vérifiée ;
 *  - GAMAD : le super administrateur.
 */
class ContributionAccess
{
    /**
     * Le bénéficiaire d'un besoin publié ou d'une proposition : [type, modèle] ; le modèle est vide pour GAMAD. Un besoin de projet
     * porte aussi la ZUMRA d'accueil du projet, qui n'est pas son bénéficiaire.
     *
     * @return array{0: BeneficiaryKind, 1: ?Model}
     */
    public function beneficiary(Publication|ContributionProposal $row): array
    {
        if ($row instanceof ContributionProposal) {
            return match ($row->beneficiaryKind()) {
                BeneficiaryKind::Gamad => [BeneficiaryKind::Gamad, null],
                BeneficiaryKind::Zumra => [BeneficiaryKind::Zumra, $row->zumra],
                BeneficiaryKind::Project => [BeneficiaryKind::Project, $row->project],
                BeneficiaryKind::Company => [BeneficiaryKind::Company, $row->company],
                BeneficiaryKind::Person => [BeneficiaryKind::Person, $row->person],
            };
        }

        return match (true) {
            (bool) $row->official => [BeneficiaryKind::Gamad, null],
            $row->project_id !== null => [BeneficiaryKind::Project, $row->project],
            $row->company_id !== null => [BeneficiaryKind::Company, $row->company],
            $row->zumra_id !== null => [BeneficiaryKind::Zumra, $row->zumra],
            default => [BeneficiaryKind::Person, $row->author],
        };
    }

    /** Cette personne agit-elle au nom de ce bénéficiaire : publier un besoin, traiter les propositions reçues ? */
    public function represents(?User $user, BeneficiaryKind $kind, ?Model $beneficiary): bool
    {
        if (! $user || ! $user->isActive()) {
            return false;
        }

        return match ($kind) {
            BeneficiaryKind::Gamad => $user->isSuperAdmin(),
            BeneficiaryKind::Person => $beneficiary instanceof User && $beneficiary->getKey() === $user->getKey(),
            BeneficiaryKind::Zumra => $beneficiary instanceof Zumra && ZumraModule::accessibleTo($user) && (bool) $beneficiary->roleOf($user)?->manages(),
            BeneficiaryKind::Project => $beneficiary instanceof Project && ZumraModule::accessibleTo($user) && $this->managesProject($user, $beneficiary),
            BeneficiaryKind::Company => $beneficiary instanceof Company && $beneficiary->verified() && $beneficiary->owner_id === $user->getKey(),
        };
    }

    /** Le porteur du projet, ou une personne de son équipe qu'il a habilitée. */
    public function managesProject(User $user, Project $project): bool
    {
        if ($project->isOwnedBy($user)) {
            return true;
        }

        return ProjectMember::query()->where('project_id', $project->getKey())->where('user_id', $user->getKey())->where('manages_contributions', true)->exists();
    }

    /** Les personnes qui reçoivent et traitent les propositions d'un bénéficiaire. */
    public function representatives(BeneficiaryKind $kind, ?Model $beneficiary): Collection
    {
        $users = match ($kind) {
            BeneficiaryKind::Gamad => User::query()->active()->where('platform_role', 'super_admin')->get(),
            BeneficiaryKind::Person => collect([$beneficiary]),
            BeneficiaryKind::Zumra => $beneficiary instanceof Zumra
                ? User::query()->whereIn('id', $beneficiary->memberships()->whereIn('role', ['founder', 'manager'])->select('user_id'))->get()
                : collect(),
            BeneficiaryKind::Project => $beneficiary instanceof Project
                ? User::query()->whereIn('id', ProjectMember::query()->where('project_id', $beneficiary->getKey())->where('manages_contributions', true)->select('user_id'))
                    ->orWhere('id', $beneficiary->owner_id)->get()
                : collect(),
            BeneficiaryKind::Company => collect([$beneficiary instanceof Company ? $beneficiary->owner : null]),
        };

        return $users->filter(fn (?User $user) => $user && $user->isActive())->values();
    }

    /** Cette personne peut-elle traiter cette proposition (l'accepter, la refuser, confirmer sa réalisation) ? */
    public function handles(?User $user, ContributionProposal $proposal): bool
    {
        [$kind, $beneficiary] = $this->beneficiary($proposal);

        return $this->represents($user, $kind, $beneficiary);
    }

    /** Cette personne gère-t-elle ce besoin ? Son auteur, ou le représentant du bénéficiaire. */
    public function manages(?User $user, Publication $need): bool
    {
        [$kind, $beneficiary] = $this->beneficiary($need);

        return $this->represents($user, $kind, $beneficiary);
    }

    /**
     * Le bénéficiaire existe-t-il pour cette personne ? Les ZUMRA et les projets suivent le module ZUMRA ; un projet n'existe que par sa
     * présentation publique (P1) ; une entreprise suspendue ou non vérifiée disparaît ; une personne supprimée ou suspendue aussi.
     */
    public function exists(?User $viewer, BeneficiaryKind $kind, ?Model $beneficiary): bool
    {
        return match ($kind) {
            BeneficiaryKind::Gamad => true,
            BeneficiaryKind::Zumra => $beneficiary instanceof Zumra && ZumraModule::accessibleTo($viewer),
            BeneficiaryKind::Project => $beneficiary instanceof Project && ZumraModule::accessibleTo($viewer) && $beneficiary->isShowcasePublished(),
            BeneficiaryKind::Company => $beneficiary instanceof Company && $beneficiary->verified(),
            BeneficiaryKind::Person => $beneficiary instanceof User && $beneficiary->isActive(),
        };
    }

    public function moduleOpen(?User $viewer): bool
    {
        return ContributionsModule::accessibleTo($viewer);
    }
}
