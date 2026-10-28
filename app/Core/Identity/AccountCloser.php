<?php

namespace App\Core\Identity;

use App\Core\Files\FilePurger;
use App\Core\Identity\Closure\AccountClosureParticipant;
use App\Core\Identity\Closure\ClosureContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Fermeture d'un compte : la seule porte de suppression d'un `User` (le modèle refuse toute autre).
 *
 *  1. dans UNE transaction : contrôles préalables de tous les participants, puis leurs préparations, puis la suppression, puis leurs
 *     suites, puis la trace de sécurité et la liste des fichiers à effacer. Toute exception annule tout ;
 *  2. après la validation : effacement des fichiers (FilePurger), repris plus tard s'il est interrompu.
 *
 * Pas de chaîne d'événements : l'ordre des participants est explicite et le résultat est tout ou rien.
 */
class AccountCloser
{
    /** Statique : le modèle consulte l'état de fermeture quelle que soit l'instance qui orchestre. @var array<string, true> */
    private static array $closing = [];

    /** @param  list<AccountClosureParticipant>  $participants  Dans l'ordre d'exécution. */
    public function __construct(private readonly array $participants, private readonly FilePurger $purger) {}

    public function isClosing(User $user): bool
    {
        return isset(self::$closing[$user->getKey()]);
    }

    public function close(User $user): void
    {
        $id = $user->getKey();
        $context = new ClosureContext;
        $purgeIds = [];
        self::$closing[$id] = true;

        try {
            DB::transaction(function () use ($user, $id, $context, &$purgeIds) {
                // Verrou du compte : deux fermetures simultanées se suivent, la seconde ne trouve plus rien à fermer.
                if (! User::query()->whereKey($id)->lockForUpdate()->exists()) {
                    return;
                }

                foreach ($this->participants as $participant) {
                    $participant->assertCanClose($user);
                }
                foreach ($this->participants as $participant) {
                    $participant->beforeDelete($user, $context);
                }

                // Ni session ni jeton de réinitialisation ne survivent au compte (D-047, D-048).
                DB::table(config('session.table', 'sessions'))->where('user_id', $id)->delete();
                DB::table('password_reset_tokens')->whereRaw('lower(email) = ?', [mb_strtolower($user->email)])->delete();

                $user->delete();

                foreach ($this->participants as $participant) {
                    $participant->afterDelete($user, $context);
                }

                IdentityEvent::record($id, IdentityEvent::ACCOUNT_CLOSED, ['files' => count($context->files())]);
                $purgeIds = $this->purger->enqueue($context->files());
            });
        } finally {
            unset(self::$closing[$id]);
        }

        // Après validation seulement. Un échec ici n'annule rien : les lignes restent et seront reprises.
        $this->purger->drain($purgeIds);
    }
}
