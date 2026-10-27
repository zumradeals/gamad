<?php

namespace App\Services\Publicite;

use App\Enums\AdState;
use App\Models\AdCampaign;
use App\Models\User;
use App\Services\People;
use App\Support\PubliciteModule;
use Illuminate\Support\Facades\DB;

/**
 * Le mécanisme de diffusion (D-210, D-211, D-212) : quelle annonce, s'il y en a une, peut être montrée à cette personne. **Tant que la diffusion
 * payante est fermée, la réponse est toujours « aucune »**. Une annonce n'est jamais montrée à son annonceur, ni entre personnes bloquées, ni
 * à une personne qui l'a masquée, ni si son annonceur est suspendu ; une annonce personnalisée exige le consentement distinct de la personne
 * et la correspondance de son profil. Le sponsoring n'entre dans aucun classement : la rotation est la même pour toutes les campagnes
 * (un ordre stable propre à la personne et au jour), le budget n'y pèse pas.
 */
class AdServing
{
    public function __construct(private readonly AudienceEstimator $audience) {}

    /** L'annonce du jour pour cette personne, ou null. */
    public function forViewer(?User $viewer): ?AdCampaign
    {
        if ($viewer === null || ! PubliciteModule::diffusionOpen() || ! PubliciteModule::accessibleTo($viewer) || $viewer->isSuspended() || ! $viewer->hasVerifiedEmail()) {
            return null;
        }

        $today = now('Africa/Abidjan')->toDateString();
        $candidates = AdCampaign::query()->with(['owner', 'company'])
            ->where('state', AdState::Ready->value)->whereDate('starts_on', '<=', $today)->whereDate('ends_on', '>=', $today)
            ->where('user_id', '!=', $viewer->getKey())->whereNotIn('user_id', People::excludedIds($viewer))
            ->whereNotIn('id', DB::table('ad_hides')->where('user_id', $viewer->getKey())->select('ad_campaign_id'))
            ->whereHas('owner', fn ($q) => $q->whereNull('suspended_at'))
            ->orderByRaw('md5(ad_campaigns.id::text || ?)', [$viewer->getKey().$today])->limit(25)->get();

        return $candidates->first(fn (AdCampaign $campaign) => $this->eligible($viewer, $campaign));
    }

    /** Cette annonce peut-elle être montrée à cette personne ? (Sert aussi à servir son image et son lien.) */
    public function eligible(User $viewer, AdCampaign $campaign): bool
    {
        if (! PubliciteModule::diffusionOpen() || ! PubliciteModule::accessibleTo($viewer) || $viewer->isSuspended() || $campaign->displayState() !== AdState::Active) {
            return false;
        }
        if ($campaign->user_id === $viewer->getKey() || $campaign->owner === null || $campaign->owner->isSuspended() || in_array($campaign->user_id, People::excludedIds($viewer), true)) {
            return false;
        }
        if (DB::table('ad_hides')->where('ad_campaign_id', $campaign->getKey())->where('user_id', $viewer->getKey())->exists()) {
            return false;
        }
        if (! $campaign->isPersonalized()) {
            return true;
        }

        $profile = $viewer->profile;

        return $profile !== null && $profile->ads_personalization && $this->audience->matches($campaign, $profile);
    }

    /** « Pourquoi cette annonce ? » : les critères que la personne reconnaît, jamais ceux des autres. */
    public function why(AdCampaign $campaign): string
    {
        if (! $campaign->isPersonalized()) {
            return 'Cette annonce n’utilise pas votre profil : elle est montrée selon l’espace où elle apparaît.';
        }

        $criteria = collect([
            $campaign->skills->isNotEmpty() ? 'compétences : '.$campaign->skills->pluck('name')->implode(', ') : null,
            $campaign->domains->isNotEmpty() ? 'domaines : '.$campaign->domains->pluck('name')->implode(', ') : null,
            ($campaign->cities->isNotEmpty() && ! $campaign->remote) ? 'lieux : '.$campaign->cities->map->label()->implode(', ') : null,
        ])->filter()->implode(' · ');

        return 'Vous avez autorisé les annonces personnalisées et votre profil correspond au ciblage de l’annonceur'.($criteria !== '' ? ' ('.$criteria.')' : '').'. L’annonceur ne voit ni votre nom ni vos données. Vous pouvez retirer cette autorisation dans Mon espace › Paramètres.';
    }
}
