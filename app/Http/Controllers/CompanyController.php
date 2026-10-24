<?php

namespace App\Http\Controllers;

use App\Models\Company;
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
        ]);
    }
}
