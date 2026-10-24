<?php

namespace App\Http\Controllers;

use App\Enums\PublicationType;
use App\Models\Profile;
use App\Models\CompanyExperience;
use App\Models\Publication;
use App\Models\WorkAttestation;
use App\Models\Zumra;
use App\Services\Contributions;
use App\Services\PersonalTrust;
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

        $personalPosts = Publication::query()->visibleTo($viewer)->whereNull('hidden_at')->whereNull('zumra_id')->where('author_id', $profile->user_id)->latest()->limit(20)->get();
        $references = collect($profile->featured_publications ?? [])->merge(collect($profile->skill_details ?? [])->pluck('publication_id'))->push($profile->cover_publication_id)->filter()->unique();
        $visibleReferences = Publication::query()->visibleTo($viewer)->whereNull('hidden_at')->whereNull('zumra_id')->where('author_id', $profile->user_id)->whereIn('id', $references)->get()->keyBy('id');

        $cover = $visibleReferences->get($profile->cover_publication_id);
        if ($cover?->media_kind !== 'image' || ! $cover->media_path) {
            $cover = null;
        }
        $showcasePosts = collect($profile->featured_publications ?? [])->map(fn ($id) => $visibleReferences->get($id))->filter()->values();
        if ($showcasePosts->isEmpty()) {
            $showcasePosts = $personalPosts->where('type', PublicationType::PersonalWork)->take(3)->values();
        }
        $currentOffer = $personalPosts->first(fn (Publication $post) => $post->type === PublicationType::Offer
            && (! ($post->matching['enabled'] ?? false) || ($post->matching['open'] ?? true))
            && (empty($post->matching['until']) || $post->matching['until'] >= today()->toDateString()));

        $companyExperiences = CompanyExperience::query()->with('company')
            ->where('user_id', $profile->user_id)->where('status', '!=', 'withdrawn')
            ->whereHas('company', fn ($query) => $query->publiclyVisible())
            ->latest()->limit(10)->get();

        return view('pages.personne', ['companyExperiences' => $companyExperiences, 'verifiedIdentity' => app(PersonalTrust::class)->identity($profile->user), 'confirmedWorks' => WorkAttestation::visibleTo($viewer)->where('author_id', $profile->user_id)->with(['attester.profile', 'author.profile'])->latest('confirmed_at')->limit(10)->get(), 'personalPosts' => $personalPosts, 'visibleReferences' => $visibleReferences, 'cover' => $cover, 'showcasePosts' => $showcasePosts, 'currentOffer' => $currentOffer, 'profile' => $profile, 'isOwner' => $isOwner, 'inviteZumras' => $inviteZumras, 'contributorBadge' => app(Contributions::class)->showsBadge($profile->user)]);
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
