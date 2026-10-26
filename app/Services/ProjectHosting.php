<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Enums\ProjectHostingState;
use App\Models\Project;
use App\Models\User;
use App\Models\Zumra;
use App\Models\ZumraMembership;
use App\Support\Normalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * L'accueil d'un projet par une ZUMRA (P1, D-206) : le porteur le demande, la ZUMRA l'accepte ou le refuse, le porteur peut retirer sa
 * demande. L'accueil ne transfère ni la propriété, ni la direction, ni les ressources du projet. Les responsables de la ZUMRA examinent
 * seulement les informations nécessaires à leur décision : jamais le dossier.
 */
class ProjectHosting
{
    public function __construct(private readonly Notifier $notifier) {}

    /**
     * Demande l'accueil du projet par une ZUMRA (la même qu'avant après un refus ou un retrait, ou une autre). Membre de cette ZUMRA, le
     * porteur est accueilli tout de suite.
     *
     * @throws ValidationException
     */
    public function request(User $owner, Project $project, Zumra $zumra, ?string $message = null): void
    {
        Gate::forUser($owner)->authorize('update', $project);
        Gate::forUser($owner)->authorize('create', [Project::class, $zumra]);

        $message = Normalizer::multiline((string) $message);

        if (mb_strlen($message) > 280) {
            throw ValidationException::withMessages(['hosting_message' => 'Le mot à la ZUMRA ne peut pas dépasser 280 caractères.']);
        }

        DB::transaction(function () use ($owner, $project, $zumra, $message) {
            $locked = Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->hosting_state === ProjectHostingState::Pending && $locked->zumra_id === $zumra->getKey()) {
                throw ValidationException::withMessages(['zumra_id' => 'La demande est déjà envoyée à cette ZUMRA.']);
            }

            if ($locked->hosting_state === ProjectHostingState::Accepted && $locked->zumra_id === $zumra->getKey()) {
                throw ValidationException::withMessages(['zumra_id' => 'Ce projet est déjà accueilli par cette ZUMRA.']);
            }

            if (Project::query()->where('zumra_id', $zumra->getKey())->where('name_key', $locked->name_key)->whereKeyNot($locked->getKey())->exists()) {
                throw ValidationException::withMessages(['zumra_id' => 'Cette ZUMRA a déjà un projet de ce nom.']);
            }

            $member = $zumra->roleOf($owner) !== null;
            $wasHosted = $locked->hosting_state === ProjectHostingState::Accepted;

            $locked->zumra()->associate($zumra);
            $locked->forceFill([
                'hosting_state' => $member ? ProjectHostingState::Accepted : ProjectHostingState::Pending,
                'hosting_message' => $member || $message === '' ? null : $message,
                'hosting_requested_at' => $member ? null : now(),
                'hosting_decided_at' => $member ? now() : null,
                'hosting_decided_by' => $member ? $owner->getKey() : null,
            ]);

            // Changer de ZUMRA retire la présentation publique : elle redevient un choix, auprès de la nouvelle ZUMRA.
            if ($wasHosted || ! $member) {
                $locked->forceFill(['showcase_published_at' => null]);
            }

            if ($member && $locked->origin_zumra_id === null) {
                $locked->forceFill(['origin_zumra_id' => $zumra->getKey()]);
            }

            $locked->save();

            if (! $member) {
                $this->notifyManagers($locked, $owner);
            }

            $project->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** Le porteur retire sa demande tant qu'elle est en attente ; le projet reste un brouillon privé. */
    public function withdraw(User $owner, Project $project): void
    {
        Gate::forUser($owner)->authorize('update', $project);

        DB::transaction(function () use ($project) {
            $locked = Project::query()->whereKey($project->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->hosting_state !== ProjectHostingState::Pending) {
                throw ValidationException::withMessages(['zumra_id' => 'Cette demande ne peut plus être retirée.']);
            }

            $locked->forceFill(['hosting_state' => ProjectHostingState::Withdrawn, 'showcase_published_at' => null])->save();
            $project->setRawAttributes($locked->getAttributes(), true);
        });
    }

    /** Un responsable de la ZUMRA demandée accepte l'accueil : le rattachement est alors confirmé, le porteur en est informé. */
    public function accept(User $manager, Project $project): void
    {
        $this->decide($manager, $project, ProjectHostingState::Accepted, NotificationKind::ProjectHostingAccepted);
    }

    /** Un responsable refuse l'accueil. Le porteur en est informé et peut demander une autre ZUMRA ; son projet reste à lui. */
    public function decline(User $manager, Project $project): void
    {
        $this->decide($manager, $project, ProjectHostingState::Declined, NotificationKind::ProjectHostingDeclined);
    }

    private function decide(User $manager, Project $project, ProjectHostingState $to, NotificationKind $kind): void
    {
        DB::transaction(function () use ($manager, $project, $to, $kind) {
            $locked = Project::query()->whereKey($project->getKey())->lockForUpdate()->with('zumra')->firstOrFail();

            Gate::forUser($manager)->authorize('decideHosting', $locked);

            if ($locked->hosting_state !== ProjectHostingState::Pending) {
                throw ValidationException::withMessages(['hosting' => 'Cette demande a déjà été traitée ou retirée.']);
            }

            $locked->forceFill(['hosting_state' => $to, 'hosting_decided_at' => now(), 'hosting_decided_by' => $manager->getKey()]);

            if ($to === ProjectHostingState::Accepted && $locked->origin_zumra_id === null) {
                $locked->forceFill(['origin_zumra_id' => $locked->zumra_id]);
            }

            $locked->save();
            $project->setRawAttributes($locked->getAttributes(), true);

            $this->notifier->push($locked->owner, $kind, $manager, project: $locked);
        });
    }

    /** Prévient le fondateur et les responsables de la ZUMRA demandée. */
    public function notifyManagers(Project $project, User $owner): void
    {
        ZumraMembership::query()->where('zumra_id', $project->zumra_id)->whereIn('role', ['founder', 'manager'])->with('user')->get()
            ->each(fn (ZumraMembership $manager) => $this->notifier->push($manager->user, NotificationKind::ProjectHostingAsked, $owner, project: $project));
    }
}
