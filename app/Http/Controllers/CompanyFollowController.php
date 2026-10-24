<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CompanyFollowController extends Controller
{
    public function __invoke(Company $company)
    {
        $user = Auth::user();
        abort_unless($user?->fresh()->isActive() && $user->hasVerifiedEmail(), 403);
        abort_unless($company->verified(), 404);

        $following = DB::table('company_follows')->where('user_id', $user->id)->where('company_id', $company->id);
        if ($following->exists()) {
            $following->delete();
        } else {
            DB::table('company_follows')->insertOrIgnore([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'created_at' => now(),
            ]);
        }

        return redirect()->route('entreprises.show', $company);
    }
}
