<?php

namespace App\Livewire;

use App\Models\Company;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Entreprises')]
class CompanyDirectory extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $search = trim(Str::limit($this->search, 100, ''));

        return view('livewire.company-directory', [
            'companies' => Company::query()->publiclyVisible()
                ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                    ->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('sector', 'ilike', '%'.addcslashes($search, '%_\\').'%')
                    ->orWhere('city', 'ilike', '%'.addcslashes($search, '%_\\').'%')))
                ->orderBy('name')->paginate(12),
        ]);
    }
}
