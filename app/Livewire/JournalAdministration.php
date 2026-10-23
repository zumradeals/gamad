<?php

namespace App\Livewire;

use App\Support\JournalReader;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Journal d'administration en lecture (D-145) : les actes du super administrateur et de la modération, les plus récents d'abord.
 * Le super administrateur lit tout ; un modérateur lit les actes de modération. Rien ne s'y modifie ni ne s'y efface.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Journal d’administration')]
class JournalAdministration extends Component
{
    use WithPagination;

    public const PER_PAGE = 25;

    #[Url(as: 'famille', except: '')]
    public string $family = '';

    public function mount(): void
    {
        Gate::authorize('moderate');
    }

    public function updatingFamily(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        Gate::authorize('moderate');

        $viewer = Auth::user();
        $families = JournalReader::familiesFor($viewer);

        if ($this->family !== '' && ! in_array($this->family, $families, true)) {
            $this->family = '';
        }

        $actions = JournalReader::query($viewer, $this->family ?: null)->paginate(self::PER_PAGE);

        return view('livewire.journal-administration', [
            'actions' => $actions,
            'subjects' => JournalReader::subjects($actions->getCollection()),
            'families' => collect($families)->mapWithKeys(fn (string $key) => [$key => JournalReader::FAMILIES[$key][0]])->all(),
        ]);
    }
}
