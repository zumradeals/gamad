<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Zumra;
use App\Services\Contributions;
use App\Support\Experience;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Monde d'une personne (écran 5) et sa photo. Les deux obéissent à la même règle de visibilité (D-067). */
class PersonController extends Controller
{
    public function show(Profile $profile)
    {
        Gate::authorize('view', $profile);

        $profile->load(['user', 'country', 'city', 'offeredSkills', 'wantedSkills', 'domains']);

        $viewer = auth()->user();
        $isOwner = $viewer?->getKey() === $profile->user_id;

        // « Inviter dans ma ZUMRA » (D-110) : les ZUMRA que le visiteur gère et où cette personne n'est pas encore.
        $inviteZumras = ! Experience::initial() && $viewer && ! $isOwner && $viewer->hasVerifiedEmail()
            ? Zumra::query()
                ->whereHas('memberships', fn ($query) => $query->where('user_id', $viewer->getKey())->whereIn('role', ['founder', 'manager']))
                ->whereDoesntHave('memberships', fn ($query) => $query->where('user_id', $profile->user_id))
                ->orderBy('name_key')->get()
            : collect();

        return view('pages.personne', ['profile' => $profile, 'isOwner' => $isOwner, 'inviteZumras' => $inviteZumras, 'contributorBadge' => app(Contributions::class)->showsBadge($profile->user)]);
    }

    /**
     * La photo repasse par la règle de visibilité à chaque affichage : « no-cache » oblige le navigateur à revalider avant de la
     * réafficher, et la revalidation (ETag) n'aboutit à « 304 » qu'après le contrôle d'accès. Un profil passé à « inscrits
     * seulement » n'est donc plus servi à un visiteur sans compte, même s'il avait déjà la photo en mémoire.
     */
    public function photo(Request $request, Profile $profile): Response
    {
        Gate::authorize('view', $profile);

        abort_unless($profile->hasPhoto() && Storage::disk(Profile::PHOTO_DISK)->exists($profile->photo_path), 404);

        $response = Storage::disk(Profile::PHOTO_DISK)->response($profile->photo_path, null, [
            'Content-Type' => 'image/jpeg',
            'X-Content-Type-Options' => 'nosniff',
        ]);

        // Le nom du fichier change à chaque nouvelle photo : il identifie donc la version sans lire le fichier.
        $response->setEtag(md5($profile->photo_path));
        $response->headers->set('Cache-Control', 'private, no-cache');
        $response->isNotModified($request);

        return $response;
    }
}
