<?php

namespace App\Livewire;

use App\Models\PublicationComment;
use App\Models\PublicationCommentReport;
use App\Services\PublicationComments;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** File séparée, accessible uniquement à la modération ; aucune identité de signalant affichée. */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Modération des commentaires')]
class ModerationCommentaires extends Component
{
    public string $tab = 'examiner';

    public array $reasons = [];

    public function mount(): void
    {
        Gate::authorize('moderate');
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'masques' ? 'masques' : 'examiner';
    }

    public function hide(string $id, PublicationComments $service): void
    {
        $service->hide(Auth::user(), $this->comment($id), $this->reasons[$id] ?? null);
        unset($this->reasons[$id]);
        session()->flash('status', 'Commentaire masqué et décision journalisée.');
    }

    public function dismiss(string $id, PublicationComments $service): void
    {
        $service->dismiss(Auth::user(), $this->comment($id), $this->reasons[$id] ?? null);
        unset($this->reasons[$id]);
        session()->flash('status', 'Signalements classés et décision journalisée.');
    }

    public function restore(string $id, PublicationComments $service): void
    {
        $service->restore(Auth::user(), $this->comment($id), $this->reasons[$id] ?? null);
        unset($this->reasons[$id]);
        session()->flash('status', 'Commentaire rétabli et décision journalisée.');
    }

    public function render()
    {
        Gate::authorize('moderate');

        return view('livewire.moderation-commentaires', [
            'toReview' => PublicationComment::query()->whereHas('reports', fn ($q) => $q->open())
                ->with(['author', 'publication', 'reports' => fn ($q) => $q->open()->orderBy('created_at')])
                ->orderBy('created_at')->limit(30)->get(),
            'hidden' => PublicationComment::query()->whereNotNull('hidden_at')->with(['author', 'publication'])
                ->orderByDesc('hidden_at')->limit(30)->get(),
            'toReviewCount' => PublicationCommentReport::query()->open()->distinct('comment_id')->count('comment_id'),
            'hiddenCount' => PublicationComment::query()->whereNotNull('hidden_at')->count(),
        ]);
    }

    private function comment(string $id): PublicationComment
    {
        Gate::authorize('moderate');
        abort_unless(Str::isUuid($id), 404);

        return PublicationComment::query()->findOrFail($id);
    }
}
