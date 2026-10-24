<?php

namespace App\Livewire;

use App\Models\PublicationBookmark;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Liste personnelle filtrée par les droits actuels : aucune fuite après masquage ou blocage. */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Mes enregistrements')]
class MesEnregistrements extends Component
{
    use WithPagination;

    public function render()
    {
        $user = Auth::user();

        return view('livewire.mes-enregistrements', [
            'bookmarks' => PublicationBookmark::query()
                ->where('user_id', $user->id)
                ->whereHas('publication', fn ($query) => $query->visibleTo($user)
                    ->whereNull('hidden_at')->whereNull('zumra_id')->whereNull('project_id'))
                ->with(['publication.author', 'publication.domain', 'publication.city'])
                ->orderByDesc('created_at')->orderByDesc('publication_id')
                ->paginate(10),
        ]);
    }
}
