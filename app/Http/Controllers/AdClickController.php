<?php

namespace App\Http\Controllers;

use App\Enums\AdCta;
use App\Models\AdCampaign;
use App\Services\Publicite\AdServing;
use App\Services\Publicite\AdStats;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Le bouton d'une annonce (D-211) : compte l'interaction, puis mène à la page de l'annonceur (profil ou page entreprise, où le geste
 * « Échanger » habituel s'applique : la personne décide) ou à l'adresse en https choisie par l'annonceur. Ne sert qu'une annonce que cette
 * personne peut voir ; sinon la page n'existe pas.
 */
class AdClickController extends Controller
{
    public function __invoke(Request $request, AdCampaign $campaign, AdServing $serving, AdStats $stats): RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($serving->eligible($viewer, $campaign), 404);
        $stats->interaction($campaign);

        if ($campaign->cta === AdCta::Link && $campaign->destination_url) {
            return redirect()->away($campaign->destination_url);
        }

        if ($campaign->company !== null && $campaign->company->verified()) {
            return redirect()->route('entreprises.show', $campaign->company);
        }

        $profile = $campaign->owner->profile;

        return $profile ? redirect()->route('personnes.show', $profile->public_id) : redirect()->route('fil');
    }
}
