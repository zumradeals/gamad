<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectCompanyLink;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Relier un projet à une entreprise GAMAD (P1, D-206) : « porté par » ou « accompagné par ». Le porteur propose, l'entreprise (son
 * propriétaire) confirme ; la ZUMRA d'origine est conservée avec le lien. La ZUMRA n'intervient pas : elle ne décide pas de la transformation d'un
 * projet. Aucun workflow juridique, aucune création automatique de société, aucune contribution financière.
 */
class ProjectCompanyLinks
{
    public function __construct(private readonly Notifier $notifier) {}

    /** @throws ValidationException */
    public function propose(User $owner, Project $project, Company $company, string $kind): ProjectCompanyLink
    {
        Gate::forUser($owner)->authorize('update', $project);

        if (! array_key_exists($kind, ProjectCompanyLink::KINDS)) {
            throw ValidationException::withMessages(['company' => 'Choisissez « porté par » ou « accompagné par ».']);
        }

        if (! Company::query()->publiclyVisible()->whereKey($company->getKey())->exists()) {
            throw ValidationException::withMessages(['company' => 'Cette entreprise ne peut pas être reliée à un projet.']);
        }

        return DB::transaction(function () use ($owner, $project, $company, $kind) {
            $link = ProjectCompanyLink::query()->where('project_id', $project->getKey())->where('company_id', $company->getKey())->first() ?? new ProjectCompanyLink;

            if ($link->exists && in_array($link->state, [ProjectCompanyLink::REQUESTED, ProjectCompanyLink::CONFIRMED], true)) {
                throw ValidationException::withMessages(['company' => 'Cette entreprise est déjà reliée ou sollicitée pour ce projet.']);
            }

            $link->fill(['kind' => $kind]);
            $link->forceFill(['state' => ProjectCompanyLink::REQUESTED, 'requested_by' => $owner->getKey(), 'decided_by' => null, 'decided_at' => null,
                'ended_at' => null, 'origin_zumra_id' => $project->origin_zumra_id ?? $project->zumra_id]);
            $link->project()->associate($project);
            $link->company()->associate($company);
            $link->save();

            $this->notifier->push($company->owner, NotificationKind::ProjectCompanyAsked, $owner, project: $project);

            return $link;
        });
    }

    /** Le propriétaire de l'entreprise confirme le lien. */
    public function confirm(User $actor, ProjectCompanyLink $link): void
    {
        $this->decide($actor, $link, ProjectCompanyLink::CONFIRMED);
    }

    /** Le propriétaire de l'entreprise décline le lien. */
    public function decline(User $actor, ProjectCompanyLink $link): void
    {
        $this->decide($actor, $link, ProjectCompanyLink::DECLINED);
    }

    /** Le porteur ou l'entreprise met fin au lien ; la trace (dates, ZUMRA d'origine) reste. */
    public function end(User $actor, ProjectCompanyLink $link): void
    {
        DB::transaction(function () use ($actor, $link) {
            $fresh = ProjectCompanyLink::query()->whereKey($link->getKey())->lockForUpdate()->with('project', 'company')->firstOrFail();

            abort_unless($fresh->project->isOwnedBy($actor) || $fresh->company->owner_id === $actor->getKey(), 404);

            if (! in_array($fresh->state, [ProjectCompanyLink::REQUESTED, ProjectCompanyLink::CONFIRMED], true)) {
                throw ValidationException::withMessages(['company' => 'Ce lien n’est plus actif.']);
            }

            $fresh->forceFill(['state' => ProjectCompanyLink::ENDED, 'ended_at' => now()])->save();
        });
    }

    private function decide(User $actor, ProjectCompanyLink $link, string $to): void
    {
        DB::transaction(function () use ($actor, $link, $to) {
            $fresh = ProjectCompanyLink::query()->whereKey($link->getKey())->lockForUpdate()->with('project.owner', 'company')->firstOrFail();

            abort_unless($fresh->company->owner_id === $actor->getKey(), 404);

            if ($fresh->state !== ProjectCompanyLink::REQUESTED) {
                throw ValidationException::withMessages(['company' => 'Cette demande a déjà été traitée.']);
            }

            $fresh->forceFill(['state' => $to, 'decided_by' => $actor->getKey(), 'decided_at' => now()])->save();

            $this->notifier->push($fresh->project->owner, NotificationKind::ProjectCompanyAnswered, $actor, project: $fresh->project);
        });
    }
}
