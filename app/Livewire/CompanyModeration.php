<?php

namespace App\Livewire;

use App\Models\Company;
use App\Services\CompanyRegistry;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Vérification des entreprises')]
class CompanyModeration extends Component
{
    public array $notes = [];

    private function authorizeReview(): void
    {
        abort_unless(Auth::user()?->isActive() && Auth::user()->isSuperAdmin(), 403);
    }

    public function mount(): void
    {
        $this->authorizeReview();
    }

    public function review(string $id, int $revision, string $action, CompanyRegistry $registry): void
    {
        $this->authorizeReview();
        $company = Company::findOrFail($id);
        $registry->review(Auth::user(), $company, $revision, $action, $this->notes[$id] ?? '');
        unset($this->notes[$id]);
        session()->flash('status', 'Décision enregistrée.');
    }

    public function render()
    {
        $this->authorizeReview();

        return view('livewire.company-moderation', [
            'companies' => Company::with(['owner', 'documents'])
                ->whereIn('status', ['pending', 'verified'])
                ->orderByRaw("case when status = 'pending' then 0 else 1 end")
                ->latest('created_at')->limit(60)->get(),
        ]);
    }
}
