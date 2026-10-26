<?php

namespace App\Support;

use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Str;

/**
 * Le service GAMAD Publicité (D-209, D-210, D-211), ouvert ou fermé depuis Paramètres par le super administrateur, sur le modèle des
 * modules ZUMRA et Contributions : désactivé (par défaut), test privé (super administrateur et testeurs à l'adresse e-mail vérifiée)
 * ou public. Aucune migration ni aucun déploiement ne l'ouvre. L'ouverture du service n'active aucune diffusion payante : celle-ci
 * dépend d'un réglage distinct, fermé tant que l'encaissement n'est pas validé.
 */
final class PubliciteModule
{
    public const OFF = 'off';

    public const TEST = 'test';

    public const PUBLIC = 'public';

    /** Hors du parcours actif (ancien mode d'expérience, tests des modules gelés), le module est ouvert comme les autres. */
    public static function state(): string
    {
        if (! Experience::initial()) {
            return self::PUBLIC;
        }

        $state = (string) app(Settings::class)->get('publicite_module');

        return in_array($state, [self::TEST, self::PUBLIC], true) ? $state : self::OFF;
    }

    /** Le service n'est ouvert qu'aux personnes connectées : jamais à un visiteur sans compte. */
    public static function accessibleTo(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return match (self::state()) {
            self::PUBLIC => true,
            self::TEST => self::isTester($user),
            default => false,
        };
    }

    public static function isTester(User $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->hasVerifiedEmail() && in_array(Str::lower($user->email), self::testers(), true);
    }

    /** @return array<int, string> */
    public static function testers(): array
    {
        return ZumraModule::parse((string) app(Settings::class)->get('publicite_testers'));
    }
}
