<?php

namespace App\Http\Controllers;

use App\Models\Profile;
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

        return view('pages.personne', ['profile' => $profile, 'isOwner' => auth()->id() === $profile->user_id]);
    }

    public function photo(Profile $profile): Response
    {
        Gate::authorize('view', $profile);

        abort_unless($profile->hasPhoto() && Storage::disk(Profile::PHOTO_DISK)->exists($profile->photo_path), 404);

        return Storage::disk(Profile::PHOTO_DISK)->response($profile->photo_path, null, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
