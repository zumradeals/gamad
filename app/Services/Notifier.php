<?php

namespace App\Services;

use App\Enums\NotificationKind;
use App\Models\Conversation;
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

        $notification = new UserNotification(['kind' => $kind]);
        $notification->user()->associate($to);
        $notification->actor()->associate($actor);
        $notification->publication()->associate($publication);
        $notification->conversation()->associate($conversation);
        $notification->zumra()->associate($zumra);
        $notification->project()->associate($project);
        $notification->save();
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
