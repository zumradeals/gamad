<?php

namespace App\Http\Controllers;

use App\Models\AdCampaign;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * L'image d'une annonce, servie seulement à son annonceur et à la modération (voir la Policy). Aucune adresse publique : un fichier privé.
 */
class AdImageController extends Controller
{
    public function __invoke(AdCampaign $campaign)
    {
        Gate::authorize('view', $campaign);
        abort_unless($campaign->image_path && Storage::disk('local')->exists($campaign->image_path), 404);

        return Storage::disk('local')->response($campaign->image_path, null, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }
}
