<?php

namespace App\Services\Publicite;

use App\Models\AdCampaign;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Compteurs et résultats (D-212). Seuls des totaux par campagne et par jour existent : aucune ligne ne dit quelle personne a vu ou cliqué.
 * Un chiffre inférieur au seuil de confidentialité est masqué (null) à l'annonceur, pour qu'aucune personne ne puisse être reconnue. Les
 * « dépenses » sont prévisionnelles (au prorata de la durée écoulée) : aucun paiement n'existe. Rien n'est compté tant que rien n'est diffusé.
 */
class AdStats
{
    /** Au-dessous, un chiffre est masqué (« — »). */
    public const THRESHOLD = 10;

    /** Dans les 24 heures suivant un clic sur l'annonce, une demande de relation envoyée à l'annonceur compte comme un contact. */
    private const CONTACT_WINDOW_HOURS = 24;

    public function impression(AdCampaign $campaign, User $viewer): void
    {
        $day = now('Africa/Abidjan')->toDateString();
        $inserted = DB::table('ad_impressions')->insertOrIgnore(['ad_campaign_id' => $campaign->getKey(), 'user_id' => $viewer->getKey(), 'day' => $day]);
        if ($inserted > 0) {
            $this->bump($campaign, 'impressions');
        }
    }

    public function interaction(AdCampaign $campaign): void
    {
        $this->bump($campaign, 'interactions');
        request()->hasSession() && session(['ad_click' => ['campaign' => $campaign->getKey(), 'at' => now()->timestamp]]);
    }

    /** Appelé quand une personne envoie une demande de relation : si elle vient d'une annonce de cet annonceur, c'est un contact. */
    public function contactFrom(User $other): void
    {
        if (! request()->hasSession()) {
            return;
        }
        $click = session('ad_click');
        if (! is_array($click) || now()->timestamp - ($click['at'] ?? 0) > self::CONTACT_WINDOW_HOURS * 3600) {
            return;
        }
        $campaign = AdCampaign::query()->with('company')->find($click['campaign'] ?? null);
        $advertiserIds = $campaign ? array_filter([$campaign->user_id, $campaign->company?->owner_id]) : [];
        if ($campaign && in_array($other->getKey(), $advertiserIds, true)) {
            $this->bump($campaign, 'contacts');
            session()->forget('ad_click');
        }
    }

    /**
     * Les résultats d'un annonceur : totaux, courbe par jour et tableau par campagne, tous masqués sous le seuil.
     *
     * @return array{totals: array<string, int|null>, series: array<int, array{day: string, impressions: int, contacts: int}>|null, rows: array<int, array<string, mixed>>, empty: bool}
     */
    public function summary(User $owner, ?string $campaignId = null, ?int $days = 30): array
    {
        $campaigns = AdCampaign::query()
            ->where(fn ($q) => $q->where('user_id', $owner->getKey())->orWhereIn('company_id', Company::query()->where('owner_id', $owner->getKey())->select('companies.id')))
            ->when($campaignId, fn ($q) => $q->whereKey($campaignId))->whereIn('state', ['ready', 'paused', 'done'])->get();

        $from = $days ? now('Africa/Abidjan')->subDays($days - 1)->toDateString() : null;
        $stats = DB::table('ad_daily_stats')->whereIn('ad_campaign_id', $campaigns->modelKeys())->when($from, fn ($q) => $q->where('day', '>=', $from))->get();

        $rows = $campaigns->map(function (AdCampaign $c) use ($stats) {
            $mine = $stats->where('ad_campaign_id', $c->getKey());
            $impressions = (int) $mine->sum('impressions');
            $interactions = (int) $mine->sum('interactions');
            $contacts = (int) $mine->sum('contacts');
            $spend = $this->forecastSpend($c);

            return [
                'campaign' => $c, 'impressions' => $this->mask($impressions), 'interactions' => $this->mask($interactions), 'contacts' => $this->mask($contacts),
                'spend' => $impressions > 0 ? $spend : null, 'perContact' => $contacts >= self::THRESHOLD && $spend ? (int) round($spend / $contacts) : null,
                'raw' => [$impressions, $interactions, $contacts],
            ];
        })->sortByDesc(fn ($row) => $row['campaign']->updated_at)->values()->all();

        $impressions = (int) $stats->sum('impressions');
        $totals = [
            'impressions' => $this->mask($impressions), 'interactions' => $this->mask((int) $stats->sum('interactions')), 'contacts' => $this->mask((int) $stats->sum('contacts')),
            'spend' => $impressions > 0 ? (int) collect($rows)->sum(fn ($row) => $row['spend'] ?? 0) : null,
        ];

        // La courbe n'est montrée que si le total est lui-même au-dessus du seuil : sinon un jour isolé pourrait désigner une personne.
        $series = $impressions >= self::THRESHOLD
            ? $stats->groupBy('day')->map(fn ($day, $key) => ['day' => (string) $key, 'impressions' => (int) $day->sum('impressions'), 'contacts' => (int) $day->sum('contacts')])->sortKeys()->values()->all()
            : null;

        return ['totals' => $totals, 'series' => $series, 'rows' => $rows, 'empty' => $stats->isEmpty()];
    }

    /** Dépense prévisionnelle : le budget au prorata des jours écoulés (jamais un montant débité). */
    public function forecastSpend(AdCampaign $campaign): ?int
    {
        if ($campaign->budget_fcfa === null || $campaign->starts_on === null || $campaign->ends_on === null) {
            return null;
        }
        $today = now('Africa/Abidjan')->startOfDay();
        if ($today->lt($campaign->starts_on)) {
            return 0;
        }
        $elapsed = Carbon::parse($campaign->starts_on)->diffInDays(min($today, Carbon::parse($campaign->ends_on))) + 1;

        return (int) round($campaign->budget_fcfa * min(1, $elapsed / max(1, (int) $campaign->durationDays())));
    }

    public function threshold(): int
    {
        return self::THRESHOLD;
    }

    private function mask(int $value): ?int
    {
        return $value >= self::THRESHOLD ? $value : null;
    }

    private function bump(AdCampaign $campaign, string $column): void
    {
        DB::statement(
            "insert into ad_daily_stats (ad_campaign_id, day, $column) values (?, ?, 1) on conflict (ad_campaign_id, day) do update set $column = ad_daily_stats.$column + 1",
            [$campaign->getKey(), now('Africa/Abidjan')->toDateString()]
        );
    }
}
