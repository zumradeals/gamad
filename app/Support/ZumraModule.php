<?php

namespace App\Support;

use App\Models\User;
use App\Services\Settings;
use Illuminate\Support\Str;

/**
 * Le module ZUMRA, ouvert ou fermé depuis le tableau de bord du super administrateur (Z1, D-205). Trois états : désactivé (par défaut),
 * test privé (super administrateur et comptes testeurs à l'adresse e-mail vérifiée) et public. Aucun déploiement ni migration ne
 * change cet état. Les Projets, les contributions et les portefeuilles ne dépendent pas de ce réglage : ils restent gelés (D-183).
 */
final class ZumraModule
{
    public const OFF = 'off';

    public const TEST = 'test';

    public const PUBLIC = 'public';

    /** Hors du parcours actif (ancien mode d'expérience, tests des modules gelés), le module est ouvert comme il l'a toujours été. */
    public static function state(): string
    {
        if (! Experience::initial()) {
            return self::PUBLIC;
        }

        $state = (string) app(Settings::class)->get('zumra_module');

        return in_array($state, [self::TEST, self::PUBLIC], true) ? $state : self::OFF;
    }

    /** Peut-on utiliser les nouvelles fonctions ZUMRA (Carrefour, création, adhésion, invitation) ? Un visiteur sans compte, seulement en public. */
    public static function accessibleTo(?User $user): bool
    {
        return match (self::state()) {
            self::PUBLIC => true,
            self::TEST => $user !== null && self::isTester($user),
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
        return self::parse((string) app(Settings::class)->get('zumra_testers'));
    }

    /** @return array<int, string> */
    public static function parse(string $raw): array
    {
        return array_values(array_unique(array_map(fn ($email) => Str::lower($email), preg_split('/[\s,;]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [])));
    }
}
