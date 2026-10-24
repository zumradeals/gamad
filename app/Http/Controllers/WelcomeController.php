<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** Entrée publique du réseau : seules des personnes et publications réellement publiques sont présentées. */
class WelcomeController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('fil');
        }

        return view('pages.welcome', [
            'people' => Profile::visibleTo(null)->where(fn ($q) => $q->whereHas('offeredSkills')->orWhereHas('wantedSkills'))
                ->with(['user', 'city', 'country', 'offeredSkills', 'wantedSkills'])
                ->orderBy(User::select('name_search')->whereColumn('users.id', 'profiles.user_id'))->orderBy('id')->limit(3)->get(),
            'posts' => Publication::visibleTo(null)->whereNull('zumra_id')->whereNull('hidden_at')
                ->whereHas('author.profile', fn ($q) => $q->visibleTo(null))
                ->with(['author.profile', 'city'])->latest()->orderByDesc('id')->limit(3)->get(),
        ]);
    }
}
