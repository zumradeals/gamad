<?php

namespace App\Livewire;

use App\Models\Company;
use App\Services\CompanyRegistry;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Corriger un dossier entreprise')]
class CompanyResubmit extends Component
{
    use WithFileUploads;

    #[Locked]
    public string $companyId;

    #[Locked]
    public int $revision;

    public $rccm = null;

    public $dfe = null;

    public $idu = null;

    public function mount(Company $company): void
    {
        abort_unless(Auth::user()?->isActive() && Auth::id() === $company->owner_id && $company->status === 'declined', 403);
        $this->companyId = $company->id;
        $this->revision = $company->revision;
    }

    public function submit(CompanyRegistry $registry)
    {
        $company = Company::findOrFail($this->companyId);
        $registry->resubmit(Auth::user(), $company, $this->revision, [
            'rccm' => $this->rccm,
            'dfe' => $this->dfe,
            'idu' => $this->idu,
        ]);

        return redirect()->route('entreprises.show', $company)
            ->with('status', 'Votre dossier corrigé a été transmis pour un nouvel examen.');
    }

    public function render()
    {
        $company = Company::findOrFail($this->companyId);
        abort_unless(Auth::user()?->isActive() && Auth::id() === $company->owner_id && $company->status === 'declined', 403);

        return view('livewire.company-resubmit', ['company' => $company]);
    }
}
