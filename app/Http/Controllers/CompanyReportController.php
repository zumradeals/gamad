<?php

namespace App\Http\Controllers;

use App\Enums\NotificationKind;
use App\Models\Company;
use App\Models\CompanyAppeal;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CompanyReportController extends Controller
{
    public function __invoke(Request $request, Company $company, Notifier $notifier)
    {
        $actor = $request->user();
        abort_unless($actor?->fresh()->isActive() && $actor->hasVerifiedEmail(), 403);
        abort_unless($company->verified() && $actor->id !== $company->owner_id, 404);
        $data = $request->validate(['reason' => 'required|string|min:20|max:1000']);
        $key = 'company-report:'.$actor->id;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw ValidationException::withMessages(['reason' => 'Limite quotidienne de signalements atteinte.']);
        }
        CompanyAppeal::create(['company_id' => $company->id, 'requester_id' => $actor->id, 'kind' => 'report', 'reason' => $data['reason'], 'status' => 'pending']);
        RateLimiter::hit($key, 86400);
        User::query()->where('platform_role', 'super_admin')->each(fn (User $admin) => $notifier->push($admin, NotificationKind::CompanyAppeal, $actor));

        return redirect()->route('entreprises.show', $company)->with('status', 'Signalement transmis à la modération.');
    }
}
