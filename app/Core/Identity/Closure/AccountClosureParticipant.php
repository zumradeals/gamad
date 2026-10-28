<?php

namespace App\Core\Identity\Closure;

use App\Models\User;

/**
 * Ce qu'un domaine doit faire quand un compte se ferme. Le Core appelle ses participants dans un ordre explicite (voir
 * AppServiceProvider), sans connaître les domaines. Les trois temps s'exécutent dans la transaction de fermeture : une exception
 * annule tout, y compris les effacements de fichiers (qui n'ont lieu qu'après validation, via ClosureContext::purgeFile()).
 */
interface AccountClosureParticipant
{
    /** Refuse la fermeture en lançant AccountClosureRefused. Appelé en premier pour tous les participants, avant toute écriture. */
    public function assertCanClose(User $user): void;

    /** Avant la suppression de la ligne du compte : transferts, effacement de contenus, fichiers à purger. */
    public function beforeDelete(User $user, ClosureContext $context): void;

    /** Après la suppression de la ligne du compte (les clés étrangères ont agi). */
    public function afterDelete(User $user, ClosureContext $context): void;
}
