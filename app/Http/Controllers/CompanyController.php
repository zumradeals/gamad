<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\CompanyExperience;
use App\Models\Publication;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyController extends Controller
{
    public function show(Company $company)
    {
        $viewer = Auth::user();
        abort_unless($company->verified() || ($viewer?->isActive() && ($viewer->id === $company->owner_id || $viewer->isSuperAdmin())), 404);

        return view('pages.entreprise', [
            'company' => $company,
            'isOwner' => $viewer?->id === $company->owner_id,
            'following' => $viewer && $company->verified() && DB::table('company_follows')
                ->where('user_id', $viewer->id)->where('company_id', $company->id)->exists(),
            'companyPosts' => $company->verified()
                ? Publication::query()->visibleTo($viewer)->where('company_id', $company->id)
                    ->whereNull('hidden_at')->with(['author.profile', 'company', 'skills', 'city', 'domain'])
                    ->latest()->limit(15)->get()
                : collect(),
            'confirmedPeople' => $company->verified()
                ? CompanyExperience::query()->where('company_id', $company->id)->where('status', 'confirmed')
                    ->where('show_on_company_page', true)
                    ->whereHas('user.profile', fn ($query) => $query->visibleTo($viewer))
                    ->with('user.profile')->latest('decided_at')->limit(12)->get()
                : collect(),
        ]);
    }
}
