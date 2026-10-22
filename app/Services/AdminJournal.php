<?php

namespace App\Services;

use App\Models\AdminAction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AdminJournal
{
    public const CONSOLE_LABEL = 'Console GAMAD';

    public const INSTALLATION_LABEL = 'Installation';

    /**
     * Inscrit un acte d'administration ou de modération. L'acteur est copié (identifiant et nom affiché)
     * pour que l'entrée reste lisible après la suppression de son compte.
     *
     * Sans acteur, l'entrée est attribuée à $actorLabel (par défaut « Console GAMAD »). Le sujet est un modèle,
     * ou, à défaut, un couple type/identifiant (par exemple une clé de réglage).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function record(
        ?User $actor,
        string $action,
        ?Model $subject = null,
        ?string $reason = null,
        array $payload = [],
        ?string $actorLabel = null,
        ?string $subjectType = null,
        ?string $subjectId = null,
    ): AdminAction {
        return AdminAction::create([
            'actor_id' => $actor?->getKey(),
            'actor_label' => $actor?->name ?? $actorLabel ?? self::CONSOLE_LABEL,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass() ?? $subjectType,
            'subject_id' => $subject?->getKey() ?? $subjectId,
            'reason' => $reason,
            'payload' => $payload ?: null,
        ]);
    }

    /**
     * Contexte d'exécution d'un acte lancé en ligne de commande : le compte système qui exécute réellement
     * la commande, le compte d'origine s'il existe (variable SUDO_USER) et la machine (D-038).
     *
     * @return array<string, string>
     */
    public static function executionContext(): array
    {
        return array_filter([
            'system_user' => posix_getpwuid(posix_geteuid())['name'] ?? 'inconnu',
            'origin_account' => getenv('SUDO_USER') ?: null,
            'machine' => gethostname() ?: null,
        ], fn ($value) => $value !== null);
    }
}
