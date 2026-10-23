<?php

namespace App\Livewire;

use App\Models\Publication;
use App\Models\Report;
use App\Services\Moderation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * File de modération (D-089) : les publications signalées à examiner, et les publications masquées avec l'explication de leur
 * auteur. Masquer, Rétablir, Classer sans suite ; chaque acte est inscrit au journal d'administration. Réservé au super
 * administrateur et aux modérateurs, revérifié à chaque affichage et à chaque acte. Écran sobre, sans maquette.
 */
#[Layout('components.layouts.app')]
#[Title('Modération')]
class FileModeration extends Component
{
    public const PAGE = 20;

    #[Url(as: 'onglet', except: 'examiner')]
    public string $tab = 'examiner';

    /** Motif saisi, par identifiant de publication. */
    public array $reasons = [];

    public function mount(): void
    {
        Gate::authorize('moderate');
    }

    public function setTab(string $tab): void
    {
        $this->tab = $tab === 'masquees' ? 'masquees' : 'examiner';
        $this->resetErrorBag();
    }

    public function hide(string $id, Moderation $moderation): void
    {
        $this->act($id, fn (Publication $publication) => $moderation->hide(Auth::user(), $publication, $this->reasons[$id] ?? null), 'Publication masquée. Son auteur en est informé.');
    }

    public function dismiss(string $id, Moderation $moderation): void
    {
        $this->act($id, fn (Publication $publication) => $moderation->dismiss(Auth::user(), $publication, $this->reasons[$id] ?? null), 'Signalements classés sans suite.');
    }

    public function restore(string $id, Moderation $moderation): void
    {
        $this->act($id, fn (Publication $publication) => $moderation->restore(Auth::user(), $publication, $this->reasons[$id] ?? null), 'Publication rétablie. Son auteur en est informé.');
    }

    public function render()
    {
        Gate::authorize('moderate');

        $toReview = Publication::query()
            ->whereHas('reports', fn ($query) => $query->open())
            ->with(['author', 'reports' => fn ($query) => $query->open()->orderBy('created_at')])
            ->withMin(['reports as first_report_at' => fn ($query) => $query->open()], 'created_at')
            ->orderBy('first_report_at')->orderBy('id')
            ->limit(self::PAGE)->get();

        $hidden = Publication::query()
            ->whereNotNull('hidden_at')
            ->with(['author', 'hider'])
            ->orderByDesc('hidden_at')->orderByDesc('id')
            ->limit(self::PAGE)->get();

        return view('livewire.file-moderation', [
            'toReview' => $toReview,
            'hidden' => $hidden,
            'toReviewCount' => Report::query()->open()->distinct('publication_id')->count('publication_id'),
            'hiddenCount' => Publication::query()->whereNotNull('hidden_at')->count(),
        ]);
    }

    private function act(string $id, callable $action, string $done): void
    {
        Gate::authorize('moderate');
        abort_unless(Str::isUuid($id), 404);

        $publication = Publication::query()->findOrFail($id);

        try {
            $action($publication);
        } catch (ValidationException $e) {
            // L'erreur s'affiche sous la publication concernée.
            throw ValidationException::withMessages(["reasons.$id" => $e->errors()['reason'] ?? array_values($e->errors())[0]]);
        }

        unset($this->reasons[$id]);
        session()->flash('status', $done);
    }
}
