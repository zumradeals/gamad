<?php

namespace App\Support;

use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Str;

/**
 * Le module Contributions (C1, D-207), ouvert ou fermé depuis le tableau de bord du super administrateur, sur le modèle du module
 * ZUMRA : désactivé (par défaut), test privé (super administrateur et testeurs à l'adresse e-mail vérifiée) ou public. Il ne rouvre
 * ni les ZUMRA ni les Projets, qui gardent leur propre état : une contribution à l'un d'eux exige aussi l'accès à cet état.
 * Aucun paiement n'existe dans ce module.
 */
final class ContributionsModule
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

        $state = (string) app(Settings::class)->get('contributions_module');

        return in_array($state, [self::TEST, self::PUBLIC], true) ? $state : self::OFF;
    }

    /** Le module n'est ouvert qu'aux personnes connectées : jamais à un visiteur sans compte. */
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
        return ZumraModule::parse((string) app(Settings::class)->get('contributions_testers'));
    }
}
