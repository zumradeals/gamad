<?php

namespace App\Http\Controllers;

use App\Services\LegalTexts;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/** Ce que voit une personne suspendue : le motif, la date, et rien d'autre à faire que se déconnecter (D-145). */
class SuspendedController extends Controller
{
    public function __invoke(LegalTexts $textes): View|RedirectResponse
    {
        $user = auth()->user();

        if (! $user->isSuspended()) {
            return redirect()->route('fil');
        }

        return view('pages.suspendu', [
            'user' => $user,
            // Le seul recours écrit est le contact des mentions légales, une fois celles-ci adoptées : jamais un contact inventé.
            'contact' => $textes->legalAdopted() ? $textes->legalValues()['legal_contact'] : null,
        ]);
    }
}
