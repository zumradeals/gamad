<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Changement du nom affiché d'une personne (D-063). Le journal d'administration garde l'ancien nom dans les entrées
 * passées (actor_label est une copie faite au moment de l'acte).
 */
class DisplayName
{
    /**
     * @return bool vrai si le nom a réellement changé
     */
    public static function change(User $user, string $name): bool
    {
        $name = trim($name);

        if ($name === $user->name) {
            return false;
        }

        DB::transaction(function () use ($user, $name) {
            // Les actes d'un modérateur ou du super administrateur sont tracés (CLAUDE.md, règle 8, D-063) : l'entrée porte
            // l'ancien nom, celui sous lequel la personne était connue au moment de l'acte.
            if ($user->can('moderate')) {
                AdminJournal::record($user, 'account.renamed_by_owner', $user, payload: ['ancien' => $user->name, 'nouveau' => $name]);
            }

            $user->update(['name' => $name]);
        });

        return true;
    }
}
