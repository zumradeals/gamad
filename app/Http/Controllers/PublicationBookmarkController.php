<?php

namespace App\Http\Controllers;

use App\Models\Publication;
use App\Services\PublicationBookmarks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PublicationBookmarkController extends Controller
{
    public function __invoke(Request $request, Publication $publication, PublicationBookmarks $bookmarks): RedirectResponse
    {
        $validated = $request->validate(['saved' => ['required', 'boolean']]);
        $save = (bool) $validated['saved'];
        $bookmarks->set($request->user(), $publication, $save);

        return back()->with('status', $save ? 'Publication enregistrée dans Mon espace.' : 'Publication retirée des enregistrements.');
    }
}
