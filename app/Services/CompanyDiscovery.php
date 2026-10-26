<?php

namespace App\Services;

use App\Exceptions\DiscoveryLimitReached;
use App\Models\Company;
use App\Models\Opportunity;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Découverte de personnes par une entreprise (lot D3, D-204) : une page de profils qui ont choisi d'être découverts et qui proposent
 * une compétence recherchée par une opportunité. La sélection vient de ComplementaryPeople, les raisons de Matching ; ce service
 * ne fait que l'autorisation, le plafond quotidien et la pagination. Aucun score, aucun classement de pertinence, aucun total.
 */
class CompanyDiscovery
{
    public const PAGE_SIZE = 10;

    public const DAILY_PAGES = 20;

    public function __construct(private ComplementaryPeople $people, private Matching $matching) {}

    /**
     * Une page de profils, avec leurs raisons, et le repère de la page suivante (null s'il n'y en a pas). Chaque appel consomme une
     * page du plafond de l'entreprise ; tout est relu à chaque appel : un consentement retiré, un compte suspendu ou un changement de
     * visibilité s'applique immédiatement, même au milieu d'une pagination.
     *
     * @return array{people: Collection<int, Profile>, next: ?string}
     *
     * @throws DiscoveryLimitReached
     */
    public function page(User $viewer, Opportunity $opportunity, ?string $cursor = null): array
    {
        Gate::forUser($viewer)->authorize('discover', $opportunity);
        $opportunity->loadMissing(['company', 'city', 'requiredSkills']);

        if (! $this->consume($opportunity->company)) {
            throw new DiscoveryLimitReached;
        }

        $seed = $this->seed($opportunity);
        $key = 'md5(profiles.id::text || ?)';
        $query = $this->people->discoverableFor($viewer, $opportunity)->with(['user', 'city', 'offeredSkills']);
        if ($cursor !== null && preg_match('/^([0-9a-f]{32})\.([0-9a-f-]{36})$/', $cursor, $part)) {
            $query->whereRaw("($key, profiles.id::text) > (?, ?)", [$seed, $part[1], $part[2]]);
        }

        $rows = $query->orderByRaw($key, [$seed])->orderBy('profiles.id')->limit(self::PAGE_SIZE + 1)->get();
        $more = $rows->count() > self::PAGE_SIZE;
        $rows = $rows->take(self::PAGE_SIZE)->values();

        $rows->each(fn ($profile) => $profile->setAttribute('reasons', $this->matching->companyReasons($opportunity, $profile)));
        $last = $rows->last();

        return ['people' => $rows, 'next' => $more && $last ? md5($last->id.$seed).'.'.$last->id : null];
    }

    /** Rotation neutre : l'ordre change chaque jour (fuseau Africa/Abidjan) et par opportunité, et reste stable pendant la journée. */
    private function seed(Opportunity $opportunity): string
    {
        return Carbon::today('Africa/Abidjan')->toDateString().'|'.$opportunity->id;
    }

    /** Une page de plus pour cette entreprise aujourd'hui, par une seule instruction atomique ; false si le plafond est atteint. */
    public function consume(Company $company): bool
    {
        $today = Carbon::today('Africa/Abidjan')->toDateString();
        DB::table('company_discovery_views')->where('company_id', $company->id)->where('day', '<', Carbon::today('Africa/Abidjan')->subDays(30)->toDateString())->delete();

        $row = DB::selectOne(
            'insert into company_discovery_views (company_id, day, views) values (?, ?, 1)
             on conflict (company_id, day) do update set views = company_discovery_views.views + 1 where company_discovery_views.views < ?
             returning views',
            [$company->id, $today, self::DAILY_PAGES],
        );

        return $row !== null;
    }
}
