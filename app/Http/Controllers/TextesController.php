<?php

namespace App\Http\Controllers;

use App\Services\LegalTexts;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Les pages publiques de la charte de conduite et des mentions légales (D-146). Tant que le Président ne les a pas adoptées, elles
 * n'existent pas pour les autres (« page introuvable ») ; lui seul les lit, marquées « Projet », pour les relire avant de les adopter.
 */
class TextesController extends Controller
{
    public function charte(LegalTexts $textes): View
    {
        $adoptee = $textes->charterAdopted();

        abort_unless($adoptee || Gate::allows('super-admin'), 404);

        return view('pages.charte', ['projet' => ! $adoptee, 'adoptee' => $textes->charterAdoptedAt()]);
    }

    public function mentions(LegalTexts $textes): View
    {
        $adoptees = $textes->legalAdopted();

        abort_unless($adoptees || Gate::allows('super-admin'), 404);

        return view('pages.mentions-legales', ['projet' => ! $adoptees, 'adoptees' => $textes->legalAdoptedAt(), 'valeurs' => $textes->legalValues()]);
    }
}
