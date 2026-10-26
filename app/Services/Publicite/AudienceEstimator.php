<?php

namespace App\Services\Publicite;

use App\Models\Profile;
use App\Models\User;
use App\Services\People;
use App\Support\PubliciteModule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Taille de l'audience d'une campagne personnalisée (D-211, D-212) : les profils qui ont consenti à la publicité personnalisée et qui
 * correspondent aux critères (compétences, domaines, villes du référentiel commun : le même que le matching, sans second moteur).
 * Les critères se cumulent entre catégories et s'additionnent dans une catégorie. Les personnes bloquées dans l'un ou l'autre sens,
 * suspendues ou à l'adresse non vérifiée, et l'annonceur lui-même, ne sont jamais comptés. Le nombre n'est jamais montré : seulement
 * son niveau. Ce service ne nomme ni ne liste personne.
 */
class AudienceEstimator
{
    public const TOO_SMALL = 'too_small';

    public const LIMITED = 'limited';

    public const FAIR = 'fair';

    public const GOOD = 'good';

    /**
     * @param  array<int, string>  $skillIds
     * @param  array<int, string>  $domainIds
     * @param  array<int, string>  $cityIds
     */
    public function count(User $advertiser, array $skillIds = [], array $domainIds = [], array $cityIds = [], bool $remote = false): int
    {
        $query = Profile::query()->where('ads_personalization', true)
            ->whereHas('user', fn (Builder $q) => $q->whereNull('suspended_at')->whereNotNull('email_verified_at')
                ->where('users.id', '!=', $advertiser->getKey())->whereNotIn('users.id', People::excludedIds($advertiser)));

        if ($skillIds !== []) {
            $query->whereExists(fn ($q) => $q->select(DB::raw(1))->from('profile_skill')
                ->whereColumn('profile_skill.profile_id', 'profiles.id')->whereIn('profile_skill.skill_id', $skillIds));
        }
        if ($domainIds !== []) {
            $query->whereExists(fn ($q) => $q->select(DB::raw(1))->from('domain_profile')
                ->whereColumn('domain_profile.profile_id', 'profiles.id')->whereIn('domain_profile.domain_id', $domainIds));
        }
        // « À distance » : la localité ne restreint pas l'audience.
        if ($cityIds !== [] && ! $remote) {
            $query->whereIn('profiles.city_id', $cityIds);
        }

        return $query->count();
    }

    /** Niveau de portée, par rapport à l'audience minimale : jamais le nombre exact. */
    public function level(int $count): string
    {
        $min = PubliciteModule::minimumAudience();

        return match (true) {
            $count < $min => self::TOO_SMALL,
            $count < $min * 3 => self::LIMITED,
            $count < $min * 10 => self::FAIR,
            default => self::GOOD,
        };
    }

    public static function label(string $level): string
    {
        return match ($level) {
            self::TOO_SMALL => 'Trop restreinte',
            self::LIMITED => 'Limitée',
            self::FAIR => 'Correcte',
            default => 'Bonne',
        };
    }

    /** Part (en %) de la jauge pour un niveau. */
    public static function gauge(string $level): int
    {
        return match ($level) {
            self::TOO_SMALL => 12,
            self::LIMITED => 36,
            self::FAIR => 62,
            default => 88,
        };
    }
}
