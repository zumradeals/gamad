<?php

namespace App\Http\Controllers;

use App\Enums\NotificationKind;
use App\Models\Company;
use App\Models\CompanyReport;
use App\Services\Notifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CompanyReportController extends Controller
{
    public function create(Company $company)
    {
        abort_unless($company->verified(), 404);
        abort_unless(auth()->user()?->isActive(), 403);

        return view('pages.company-report', ['company' => $company]);
    }

    public function store(Request $request, Company $company, Notifier $notifier)
    {
        $actor = $request->user();
        abort_unless($actor?->fresh()->isActive(), 403);
        abort_unless($company->fresh()->verified(), 404);
        abort_if($actor->id === $company->owner_id, 403);
        $data = $request->validate([
            'reason' => ['required', Rule::in(['impersonation', 'wrong_information', 'other'])],
            'details' => ['required', 'string', 'min:15', 'max:1000'],
        ]);
        if (RateLimiter::tooManyAttempts('company-report:'.$actor->id, 5)) {
            throw ValidationException::withMessages(['details' => 'Trop de signalements envoyés aujourd’hui.']);
        }
        if (CompanyReport::where('company_id', $company->id)->where('reporter_id', $actor->id)->exists()) {
            throw ValidationException::withMessages(['details' => 'Vous avez déjà signalé cette page. Votre signalement est conservé.']);
        }

        $report = new CompanyReport([
            'reason' => $data['reason'],
            'details' => trim($data['details']),
        ]);
        $report->company_id = $company->id;
        $report->reporter_id = $actor->id;
        try {
            $report->save();
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['details' => 'Vous avez déjà signalé cette page.']);
        }
        RateLimiter::hit('company-report:'.$actor->id, 86400);
        $notifier->companyModerators(NotificationKind::CompanyReportReceived, $actor, $company);

        return redirect()->route('entreprises.show', $company)
            ->with('status', 'Votre signalement a été transmis à GAMAD pour examen.');
    }
}
