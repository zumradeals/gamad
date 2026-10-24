<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyBranding;
use Illuminate\Http\Request;

class CompanyBrandingController extends Controller
{
    private function assertOwner(Company $company): void
    {
        $user = auth()->user();
        abort_unless($user?->fresh()->isActive() && $user->hasVerifiedEmail()
            && $company->owner_id === $user->id, 403);
    }

    public function edit(Company $company)
    {
        $this->assertOwner($company);

        return view('pages.company-branding', ['company' => $company]);
    }

    public function update(Request $request, Company $company, CompanyBranding $service)
    {
        $this->assertOwner($company);
        $service->update($request->user(), $company, $request->all());

        return redirect()->route('entreprises.show', $company)
            ->with('status', 'La présentation et les visuels de votre entreprise ont été mis à jour.');
    }
}
