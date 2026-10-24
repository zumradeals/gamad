<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Services\CompanyExperienceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CompanyExperienceReviewController extends Controller
{
    public function index(Company $company)
    {
        $actor = Auth::user();
        abort_unless($actor?->isActive() && $actor->id === $company->owner_id, 403);
        abort_unless($company->verified(), 404);

        return view('pages.company-experience-review', [
            'company' => $company,
            'requests' => CompanyExperience::where('company_id', $company->id)
                ->where('status', 'pending')->with('user.profile')->latest()->limit(100)->get(),
        ]);
    }

    public function decide(Request $request, Company $company, CompanyExperience $experience, CompanyExperienceService $service)
    {
        abort_unless($company->id === $experience->company_id, 404);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'decision' => ['required', 'in:confirm,decline'],
        ]);
        $service->decide(Auth::user(), $experience, (int) $data['revision'], $data['decision']);

        return redirect()->route('entreprises.experiences.review', $company)->with('status', 'Réponse enregistrée.');
    }
}
