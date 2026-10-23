<?php

namespace App\Livewire;

use App\Enums\ReportCategory;
use App\Models\Publication;
use App\Models\Report;
use App\Services\Reports;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Signaler une publication (écran 16). Une personne connectée et vérifiée, jamais pour sa propre publication. */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Signaler')]
class SignalerPublication extends Component
{
    #[Locked]
    public string $publicationId;

    public string $category = '';

    public string $details = '';

    public function mount(Publication $publication): void
    {
        Gate::authorize('report', $publication);

        $this->publicationId = $publication->id;
    }

    public function send(Reports $reports)
    {
        $reports->submit(Auth::user(), $this->publication(), $this->category, $this->details);

        session()->flash('status', 'Merci. Un modérateur examinera votre signalement.');

        return $this->redirect(route('publications.show', $this->publicationId), navigate: false);
    }

    public function render()
    {
        $publication = $this->publication()->load(['author.profile']);

        return view('livewire.signaler-publication', [
            'publication' => $publication,
            'categories' => ReportCategory::cases(),
            'already' => Report::query()->where('publication_id', $publication->id)->where('reporter_id', Auth::id())->exists(),
        ]);
    }

    private function publication(): Publication
    {
        $publication = Publication::query()->findOrFail($this->publicationId);

        Gate::authorize('report', $publication);

        return $publication;
    }
}
