<?php

namespace App\Livewire;

use App\Models\Company;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Entreprise')]
class CompanyPage extends Component
{
    #[Locked]
    public string $companyId;

    public function mount(Company $company): void
    {
        $viewer = Auth::user();
        abort_unless($company->verified() || ($viewer?->isActive() && ($viewer->id === $company->owner_id || $viewer->isSuperAdmin())), 404);
        $this->companyId = $company->id;
    }

    public function toggleFollow(): void
    {
        $viewer = Auth::user();
        abort_unless($viewer?->fresh()->isActive() && $viewer->hasVerifiedEmail(), 403);
        $company = Company::findOrFail($this->companyId);
        abort_unless($company->verified(), 404);

        $following = DB::table('company_follows')->where('user_id', $viewer->id)->where('company_id', $company->id);
        if ($following->exists()) {
            $following->delete();
        } else {
            DB::table('company_follows')->insertOrIgnore([
                'user_id' => $viewer->id,
                'company_id' => $company->id,
                'created_at' => now(),
            ]);
        }
    }

    public function render()
    {
        $viewer = Auth::user();
        $company = Company::findOrFail($this->companyId);
        abort_unless($company->verified() || ($viewer?->isActive() && ($viewer->id === $company->owner_id || $viewer->isSuperAdmin())), 404);

        return view('livewire.company-page', [
            'company' => $company,
            'isOwner' => $viewer?->id === $company->owner_id,
            'following' => $viewer && $company->verified() && DB::table('company_follows')->where('user_id', $viewer->id)->where('company_id', $company->id)->exists(),
        ]);
    }
}
