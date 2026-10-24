<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyRegistry;
use Illuminate\Http\Request;

class CompanyResubmitController extends Controller
{
    private function authorizeCorrection(Company $company): void
    {
        $user = auth()->user();
        abort_unless($user?->fresh()->isActive() && $user->hasVerifiedEmail()
            && $company->owner_id === $user->id && $company->status === 'declined', 403);
    }

    public function edit(Company $company)
    {
        $this->authorizeCorrection($company);

        return view('pages.company-resubmit', ['company' => $company]);
    }

    public function submit(Request $request, Company $company, CompanyRegistry $registry)
    {
        $this->authorizeCorrection($company);
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'rccm' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'dfe' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'idu' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $registry->resubmit($request->user(), $company, (int) $data['revision'], $request->only(['rccm', 'dfe', 'idu']));

        return redirect()->route('entreprises.show', $company)
            ->with('status', 'Votre dossier corrigé a été transmis pour un nouvel examen.');
    }
}
