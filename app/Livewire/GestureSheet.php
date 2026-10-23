<?php

namespace App\Livewire;

use App\Models\Gesture;
use App\Models\Publication;
use App\Services\Gestures;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Feuille « Je peux aider » (écran 10) : propose un geste sur une publication, avec un mot facultatif de 280 caractères ; sert
 * aussi à retirer son geste. Toutes les règles sont vérifiées par App\Services\Gestures, côté serveur.
 */
class GestureSheet extends Component
{
    public ?string $publicationId = null;

    public string $message = '';

    #[On('open-gesture')]
    public function open(string $id): void
    {
        abort_unless(Str::isUuid($id), 404);

        $publication = Publication::query()->with('author')->findOrFail($id);
        Gate::authorize('gesture', $publication);

        $this->reset('message');
        $this->resetErrorBag();
        $this->publicationId = $publication->id;
    }

    public function close(): void
    {
        $this->reset('publicationId', 'message');
        $this->resetErrorBag();
    }

    public function send(): void
    {
        $publication = Publication::query()->findOrFail($this->publicationId);

        app(Gestures::class)->propose(Auth::user(), $publication, $this->message);

        $this->close();
        $this->dispatch('gesture-changed');
    }

    #[On('withdraw-gesture')]
    public function withdraw(string $id): void
    {
        abort_unless(Str::isUuid($id), 404);

        $gesture = Gesture::query()->where('publication_id', $id)->where('user_id', Auth::id())->firstOrFail();

        app(Gestures::class)->withdraw(Auth::user(), $gesture);

        $this->dispatch('gesture-changed');
    }

    public function render()
    {
        return view('livewire.gesture-sheet', [
            'publication' => $this->publicationId ? Publication::query()->with('author')->find($this->publicationId) : null,
        ]);
    }
}
