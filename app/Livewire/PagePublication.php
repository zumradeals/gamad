<?php

namespace App\Livewire;

use App\Enums\GestureState;
use App\Models\Gesture;
use App\Models\Publication;
use App\Services\Gestures;
use App\Services\Publications;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Page d'une publication. Pour son auteur, c'est l'écran « Mon besoin » (écran 11) : les réponses reçues, avec Accepter et
 * Décliner, puis « Marquer comme résolu » et « Fermer sans suite ». Pour les autres, la publication seule et le bouton de réponse.
 * Une publication qu'on n'a pas le droit de voir répond 404 (D-085).
 */
#[Layout('components.layouts.app')]
class PagePublication extends Component
{
    public string $publicationId;

    public function mount(Publication $publication): void
    {
        Gate::authorize('view', $publication);

        $this->publicationId = $publication->id;
    }

    #[On('gesture-changed')]
    public function refresh(): void {}

    public function accept(string $gestureId, Gestures $gestures): void
    {
        $gestures->accept(Auth::user(), $this->gesture($gestureId));
    }

    public function decline(string $gestureId, Gestures $gestures): void
    {
        $gestures->decline(Auth::user(), $this->gesture($gestureId));
    }

    public function resolve(Publications $publications): void
    {
        $publications->resolve(Auth::user(), $this->publication());
        session()->flash('status', 'Besoin marqué comme résolu.');
    }

    public function close(Publications $publications): void
    {
        $publications->close(Auth::user(), $this->publication());
        session()->flash('status', 'Besoin fermé sans suite.');
    }

    public function render()
    {
        $viewer = Auth::user();
        $publication = $this->publication()->load(['author.profile', 'domain', 'city', 'skills']);
        $publication->live_gestures_count = $publication->liveGesturesCount();
        $type = $publication->type;
        $isAuthor = $viewer && $viewer->getKey() === $publication->author_id;

        return view('livewire.page-publication', [
            'publication' => $publication,
            'type' => $type,
            'viewer' => $viewer,
            'isAuthor' => $isAuthor,
            'gesture' => $viewer && ! $isAuthor ? Gesture::query()->where('publication_id', $publication->id)->where('user_id', $viewer->getKey())->first() : null,
            // Les gestes reçus : ceux en attente d'abord, puis acceptés, puis déclinés ; les gestes retirés disparaissent.
            'received' => $isAuthor
                ? $publication->gestures()
                    ->where('state', '!=', GestureState::Withdrawn->value)
                    ->with(['author.profile.city', 'author.profile.offeredSkills'])
                    ->orderByRaw("case state when 'sent' then 0 when 'accepted' then 1 else 2 end")
                    ->orderBy('created_at')
                    ->get()
                : collect(),
        ])->title($isAuthor ? $type->ownTitle() : $type->label());
    }

    private function publication(): Publication
    {
        return Publication::query()->findOrFail($this->publicationId);
    }

    private function gesture(string $id): Gesture
    {
        abort_unless(Str::isUuid($id), 404);

        return Gesture::query()->where('publication_id', $this->publicationId)->findOrFail($id);
    }
}
