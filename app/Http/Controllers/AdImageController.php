<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use App\Services\Publicite\AdServing;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * L'image d'une annonce, servie à son annonceur, à la modération (voir la Policy) et à la personne à qui l'annonce est diffusée (AdServing). Aucune adresse publique : un fichier privé.
 */
class AdImageController extends Controller
{
    public function __invoke(AdCampaign $campaign)
    {
        if (! Gate::allows('view', $campaign) && ! app(AdServing::class)->eligible(auth()->user(), $campaign)) {
            abort(404);
        }
        abort_unless($campaign->image_path && Storage::disk('local')->exists($campaign->image_path), 404);

        return Storage::disk('local')->response($campaign->image_path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }
}
