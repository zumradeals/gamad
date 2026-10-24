<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Enums\NotificationKind;
use App\Models\User;
use App\Services\Notifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class CompanyAppealController extends Controller
{
    public function __invoke(Request $request, Company $company, Notifier $notifier)
    {
        $actor = $request->user();
        abort_unless($actor?->isActive() && $actor->hasVerifiedEmail(), 403);
        abort_unless($company->owner_id === $actor->id, 403);
        abort_unless(in_array($company->status, ['declined', 'suspended'], true), 422);

        $data = $request->validate(['reason' => 'required|string|min:20|max:1000']);
        $key = 'company-appeal:'.$company->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            throw ValidationException::withMessages(['reason' => 'Un recours récent a déjà été transmis.']);
        }

        // Ne réactive jamais la page : la modération conserve la décision.
        $company->update(['review_note' => 'Recours transmis par le propriétaire : '.$data['reason']]);
        RateLimiter::hit($key, 86400);
        User::query()->where('platform_role', 'super_admin')->each(
            fn (User $admin) => $notifier->push($admin, NotificationKind::CompanyRequest, $actor)
        );

        return redirect()->route('entreprises.show', $company)->with('status', 'Recours transmis. La page reste non publique pendant son examen.');
    }
}
