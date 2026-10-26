<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Enums\PlatformRole;
use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\ContributionProposal;
use App\Models\Conversation;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Publication;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\Zumra;

/**
 * Écrit les notifications de l'application (écran 15). Personne n'est jamais notifié de son propre acte ; un message ne
 * crée qu'une notification tant que la précédente, pour la même conversation, n'est pas lue (pas de pluie d'alertes).
 */
class Notifier
{
    public function push(?User $to, NotificationKind $kind, ?User $actor = null, ?Publication $publication = null, ?Conversation $conversation = null, ?Zumra $zumra = null, ?Project $project = null): void
    {
        if ($to === null || $actor?->getKey() === $to->getKey()) {
            return;
        }

        if ($kind === NotificationKind::MessageReceived && $conversation) {
            $pending = UserNotification::query()
                ->where('user_id', $to->getKey())
                ->where('kind', $kind->value)
                ->where('conversation_id', $conversation->getKey())
                ->whereNull('read_at')
                ->exists();

            if ($pending) {
                return;
            }
        }

        // Une explication réécrite ne prévient pas deux fois la modération tant que la première n'est pas lue.
        if ($kind->isForModeration() && UserNotification::query()
            ->where('user_id', $to->getKey())->where('kind', $kind->value)->whereNull('read_at')
            ->where('actor_id', $actor?->getKey())->where('publication_id', $publication?->getKey())->exists()) {
            return;
        }

        $notification = new UserNotification(['kind' => $kind]);
        $notification->user()->associate($to);
        $notification->actor()->associate($actor);
        $notification->publication()->associate($publication);
        $notification->conversation()->associate($conversation);
        $notification->zumra()->associate($zumra);
        $notification->project()->associate($project);
        $notification->save();
    }

    /** Une proposition de contribution ou la suite qui lui est donnée : renvoie à la proposition (ou à la conversation ouverte). */
    public function proposal(?User $to, NotificationKind $kind, ?User $actor, ContributionProposal $proposal, ?Conversation $conversation = null): void
    {
        if ($to === null || $actor?->getKey() === $to->getKey()) {
            return;
        }

        $alert = new UserNotification(['kind' => $kind]);
        $alert->user()->associate($to);
        $alert->actor()->associate($actor);
        $alert->contributionProposal()->associate($proposal);
        $alert->conversation()->associate($conversation);
        $alert->save();
    }

    /** Les alertes entreprise renvoient au dossier concerné, jamais à une page générique. */
    public function company(?User $to, NotificationKind $kind, ?User $actor, Company $company, ?CompanyExperience $experience = null): void
    {
        if ($to === null || $actor?->id === $to->id) {
            return;
        }
        $alert = new UserNotification(['kind' => $kind]);
        $alert->user()->associate($to);
        $alert->actor()->associate($actor);
        $alert->company()->associate($company);
        $alert->companyExperience()->associate($experience);
        $alert->save();
    }

    /** Une réponse à une opportunité, ou la suite que l'entreprise y donne : renvoie à l'opportunité concernée. */
    public function opportunity(?User $to, NotificationKind $kind, ?User $actor, Opportunity $opportunity): void
    {
        if ($to === null || $actor?->id === $to->id) {
            return;
        }
        $alert = new UserNotification(['kind' => $kind]);
        $alert->user()->associate($to);
        $alert->actor()->associate($actor);
        $alert->company()->associate($opportunity->company);
        $alert->opportunity()->associate($opportunity);
        $alert->save();
    }

    public function companyModerators(NotificationKind $kind, User $actor, Company $company): void
    {
        User::active()->where('platform_role', PlatformRole::SuperAdmin->value)
            ->each(fn (User $reviewer) => $this->company($reviewer, $kind, $actor, $company));
    }

    /** Prévient toute la modération (modérateurs et super administrateur), sauf la personne qui agit. */
    public function pushToModeration(NotificationKind $kind, User $actor, ?Publication $publication = null): void
    {
        User::query()->whereIn('platform_role', [PlatformRole::Moderator->value, PlatformRole::SuperAdmin->value])
            ->each(fn (User $moderator) => $this->push($moderator, $kind, $actor, $publication));
    }

    /** Marque comme lues les notifications de messages d'une conversation (la personne vient de la lire). */
    public function readConversation(User $user, Conversation $conversation): void
    {
        UserNotification::query()
            ->where('user_id', $user->getKey())
            ->where('conversation_id', $conversation->getKey())
            ->where('kind', NotificationKind::MessageReceived->value)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);
    }

    public function readAll(User $user): void
    {
        UserNotification::query()->where('user_id', $user->getKey())->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function unreadCount(User $user): int
    {
        return UserNotification::query()->where('user_id', $user->getKey())->whereNull('read_at')->count();
    }
}
