<?php

namespace App\Services;

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AdminJournal
{
    public const CONSOLE_LABEL = 'Console GAMAD';

    /**
     * Inscrit un acte d'administration ou de modération. L'acteur est copié (identifiant et nom affiché)
     * pour que l'entrée reste lisible après la suppression de son compte.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function record(?User $actor, string $action, ?Model $subject = null, ?string $reason = null, array $payload = []): AdminAction
    {
        return AdminAction::create([
            'actor_id' => $actor?->getKey(),
            'actor_label' => $actor?->name ?? self::CONSOLE_LABEL,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'reason' => $reason,
            'payload' => $payload ?: null,
        ]);
    }
}
