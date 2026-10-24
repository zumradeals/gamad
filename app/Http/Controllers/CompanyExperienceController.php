<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Services\CompanyExperienceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyExperienceController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        abort_unless($user?->isActive(), 403);

        return view('pages.company-experiences', [
            'companies' => Company::publiclyVisible()->orderBy('name')->limit(300)->get(['id', 'name', 'sector']),
            'experiences' => CompanyExperience::with('company')
                ->where('user_id', $user->id)->where('status', '!=', 'withdrawn')
                ->latest()->get(),
            'types' => CompanyExperience::TYPES,
        ]);
    }

    public function store(Request $request, CompanyExperienceService $service)
    {
        $service->declare(Auth::user(), $request->all());

        return redirect()->route('espace.company-experiences')->with('status', 'Expérience enregistrée. La confirmation de l’entreprise reste en attente.');
    }

    public function withdraw(CompanyExperience $experience, CompanyExperienceService $service)
    {
        $service->withdraw(Auth::user(), $experience);

        return redirect()->route('espace.company-experiences')->with('status', 'Expérience retirée de votre parcours.');
    }

    public function visibility(Request $request, CompanyExperience $experience, CompanyExperienceService $service)
    {
        $data = $request->validate(['visible' => ['required', 'boolean']]);
        $service->companyVisibility(Auth::user(), $experience, (bool) $data['visible']);

        return redirect()->route('espace.company-experiences')->with('status', 'Visibilité mise à jour.');
    }
}
