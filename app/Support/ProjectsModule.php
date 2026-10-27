<?php

namespace App\Support;

use App\Models\User;
use App\Services\Settings;

/**
 * Le module Projets, avec son propre interrupteur (recette ZUMRA, D-213). Jusqu'ici les Projets n'avaient pas de réglage : ils suivaient
 * l'état du module ZUMRA (D-205, D-206), sans qu'on puisse ouvrir les ZUMRA et garder les Projets fermés. Quatre états : « suit le module
 * ZUMRA » (par défaut : rien ne change pour l'existant), désactivé, test privé (mêmes testeurs que les ZUMRA) ou public. Les Projets ne
 * s'ouvrent jamais sans les ZUMRA, qui les accueillent. Fermés, ils n'existent pour personne (404) ; les données et les projets existants
 * sont conservés et se retrouvent à la réouverture.
 */
final class ProjectsModule
{
    public const INHERIT = 'inherit';

    public const OFF = 'off';

    public const TEST = 'test';

    public const PUBLIC = 'public';

    /** Le réglage choisi par le Président (« inherit » tant qu'il n'a rien changé). */
    public static function setting(): string
    {
        $state = (string) app(Settings::class)->get('projets_module');

        return in_array($state, [self::OFF, self::TEST, self::PUBLIC], true) ? $state : self::INHERIT;
    }

    public static function accessibleTo(?User $user): bool
    {
        if (! ZumraModule::accessibleTo($user)) {
            return false;
        }

        // Hors du parcours actif (ancien mode d'expérience, tests des modules gelés), les Projets suivent les ZUMRA comme avant.
        if (! Experience::initial()) {
            return true;
        }

        return match (self::setting()) {
            self::OFF => false,
            self::TEST => $user !== null && ZumraModule::isTester($user),
            default => true,
        };
    }
}
