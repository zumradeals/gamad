<?php

namespace App\Livewire;

use App\Models\Company;
use App\Services\CompanyRegistry;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Créer ma page entreprise')]
class CompanyCreate extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $legalName = '';

    public string $country = 'CI';

    public string $registration = '';

    public string $sector = '';

    public string $city = '';

    public string $website = '';

    public string $email = '';

    public string $summary = '';

    public string $description = '';

    public $rccm = null;

    public $dfe = null;

    public $idu = null;

    public function save(CompanyRegistry $registry)
    {
        $company = $registry->submit(Auth::user(), [
            'name' => $this->name,
            'legal_name' => $this->legalName,
            'registration_country' => $this->country,
            'registration_number' => $this->registration,
            'sector' => $this->sector,
            'city' => $this->city ?: null,
            'website' => $this->website ?: null,
            'email' => $this->email ?: null,
            'summary' => $this->summary ?: null,
            'description' => $this->description ?: null,
        ], array_filter(['rccm' => $this->rccm, 'dfe' => $this->dfe, 'idu' => $this->idu]));

        session()->flash('status', 'Votre demande est enregistrée. La page restera privée jusqu’au contrôle des documents.');
        return redirect()->route('entreprises.show', $company);
    }

    public function render()
    {
        abort_unless(Auth::user()?->isActive(), 403);

        return view('livewire.company-create', [
            'myCompanies' => Company::where('owner_id', Auth::id())->orderByDesc('created_at')->get(),
        ]);
    }
}
