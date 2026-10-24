<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\People;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class CompanyContactController extends Controller
{
    /** Une demande de relation, pas un message privé imposé à l'entreprise. */
    public function create(Company $company)
    {
        $actor = Auth::user();
        abort_unless($actor?->isActive() && $actor->hasVerifiedEmail(), 403);
        abort_unless($company->verified(), 404);
        abort_if($company->owner_id === $actor->id, 403);

        return view('pages.company-contact', ['company' => $company]);
    }

    public function store(Request $request, Company $company, People $people)
    {
        $actor = Auth::user();
        abort_unless($actor?->fresh()->isActive() && $actor->hasVerifiedEmail(), 403);
        abort_unless($company->fresh()->verified(), 404);
        abort_if($company->owner_id === $actor->id, 403);
        $data = $request->validate([
            'note' => ['required', 'string', 'min:5', 'max:210'],
        ]);

        // Réutilise la relation bilatérale existante : protections anti-spam,
        // blocage mutuel, demande et consentement avant ouverture d'une conversation.
        $people->request($actor, $company->owner, 'Entreprise '.Str::limit($company->name, 45, '').' : '.trim($data['note']));

        return redirect()->route('entreprises.show', $company)
            ->with('status', 'Votre demande de mise en relation a été envoyée. Aucun échange privé ne démarre sans acceptation.');
    }
}
