<?php

namespace App\Livewire;

use App\Models\Gesture;
use App\Models\Publication;
use App\Services\Gestures;
use App\Services\Supports;
use App\Support\Normalizer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Feuille « Je peux aider » (écran 10) : propose un geste sur une publication, avec un mot facultatif de 280 caractères ; sert
 * aussi à retirer son geste. Toutes les règles sont vérifiées par App\Services\Gestures, côté serveur.
 */
class GestureSheet extends Component
{
    /** Verrouillé : le navigateur ne peut pas changer de publication ; le droit de voir est de plus revérifié à chaque affichage. */
    #[Locked]
    public ?string $publicationId = null;

    public string $message = '';

    public string $availability = '';

    public string $helpFormat = '';

    #[On('open-gesture')]
    public function open(string $id): void
    {
        abort_unless(Str::isUuid($id), 404);

        $publication = Publication::query()->with('author')->findOrFail($id);
        Gate::authorize('gesture', $publication);

        $this->reset('message', 'availability', 'helpFormat');
        $this->resetErrorBag();
        $this->publicationId = $publication->id;
    }

    public function close(): void
    {
        $this->reset('publicationId', 'message', 'availability', 'helpFormat');
        $this->resetErrorBag();
    }

    public function send(): void
    {
        $publication = Publication::query()->findOrFail($this->publicationId);

        $this->validate(['availability' => ['nullable', 'string', 'max:100'], 'helpFormat' => ['nullable', 'in:onsite,remote']], [], ['availability' => 'disponibilités', 'helpFormat' => 'forme d’aide']);
        $parts = [Normalizer::multiline($this->message)];
        if (filled($this->availability)) {
            $parts[] = 'Disponibilités : '.Normalizer::display($this->availability);
        }
        if ($this->helpFormat !== '') {
            $parts[] = 'Format : '.($this->helpFormat === 'remote' ? 'à distance' : 'sur place');
        }
        app(Gestures::class)->propose(Auth::user(), $publication, implode("\n", array_filter($parts, fn ($part) => $part !== '')));

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

    /** « Je soutiens » sur une avancée de projet (D-139). */
    #[On('support')]
    public function support(string $id): void
    {
        abort_unless(Str::isUuid($id), 404);

        app(Supports::class)->support(Auth::user(), Publication::query()->find($id) ?? abort(404));

        $this->dispatch('gesture-changed');
    }

    #[On('unsupport')]
    public function unsupport(string $id): void
    {
        abort_unless(Str::isUuid($id), 404);

        app(Supports::class)->withdraw(Auth::user(), Publication::query()->find($id) ?? abort(404));

        $this->dispatch('gesture-changed');
    }

    public function render()
    {
        $publication = $this->publicationId ? Publication::query()->with(['author', 'zumra'])->find($this->publicationId) : null;

        // Une publication qu'on n'a pas le droit de voir n'est jamais affichée, même si l'identifiant a été changé en route.
        if ($publication && ! Gate::allows('view', $publication)) {
            $this->reset('publicationId', 'message', 'availability', 'helpFormat');
            $publication = null;
        }

        return view('livewire.gesture-sheet', ['publication' => $publication]);
    }
}
