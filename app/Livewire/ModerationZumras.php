<?php

namespace App\Livewire;

use App\Models\Zumra;
use App\Models\ZumraPost;
use App\Models\ZumraReport;
use App\Services\ZumraModeration;
use App\Support\ZumraModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** File de la modération des ZUMRA (D-215) : ZUMRA et messages signalés, ZUMRA suspendues, messages masqués. Aucune identité de signalant. */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Modération des ZUMRA')]
class ModerationZumras extends Component
{
    public string $tab = 'examiner';

    /** @var array<string, string> */
    public array $reasons = [];

    public function mount(): void
    {
        Gate::authorize('moderate');
        abort_unless(ZumraModule::accessibleTo(Auth::user()), 404);
    }

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, ['examiner', 'suspendues'], true) ? $tab : 'examiner';
    }

    public function suspend(string $id, ZumraModeration $moderation): void
    {
        $moderation->suspend(Auth::user(), $this->zumra($id), $this->reasons[$id] ?? null);
        unset($this->reasons[$id]);
        session()->flash('status', 'ZUMRA suspendue et décision journalisée.');
    }

    public function reinstate(string $id, ZumraModeration $moderation): void
    {
        $moderation->reinstate(Auth::user(), $this->zumra($id), $this->reasons[$id] ?? null);
        unset($this->reasons[$id]);
        session()->flash('status', 'ZUMRA rétablie et décision journalisée.');
    }

    public function dismissZumra(string $id, ZumraModeration $moderation): void
    {
        $moderation->dismissZumra(Auth::user(), $this->zumra($id), $this->reasons[$id] ?? null);
        unset($this->reasons[$id]);
        session()->flash('status', 'Signalements classés et décision journalisée.');
    }

    public function hidePost(string $id, ZumraModeration $moderation): void
    {
        $moderation->hidePost(Auth::user(), $this->post($id), $this->reasons[$id] ?? null);
        unset($this->reasons[$id]);
        session()->flash('status', 'Message masqué et décision journalisée.');
    }

    public function restorePost(string $id, ZumraModeration $moderation): void
    {
        $moderation->restorePost(Auth::user(), $this->post($id), $this->reasons[$id] ?? null);
        unset($this->reasons[$id]);
        session()->flash('status', 'Message rétabli et décision journalisée.');
    }

    public function dismissPost(string $id, ZumraModeration $moderation): void
    {
        $moderation->dismissPost(Auth::user(), $this->post($id), $this->reasons[$id] ?? null);
        unset($this->reasons[$id]);
        session()->flash('status', 'Signalements classés et décision journalisée.');
    }

    public function render()
    {
        Gate::authorize('moderate');

        return view('livewire.moderation-zumras', [
            'zumras' => Zumra::query()->whereNull('suspended_at')
                ->whereHas('reports', fn ($q) => $q->open()->whereNull('zumra_post_id'))
                ->with(['reports' => fn ($q) => $q->open()->whereNull('zumra_post_id')->orderBy('created_at')])
                ->orderBy('name_key')->limit(30)->get(),
            'posts' => ZumraPost::query()->whereNull('hidden_at')
                ->whereHas('reports', fn ($q) => $q->open())
                ->with(['author', 'zumra', 'reports' => fn ($q) => $q->open()->orderBy('created_at')])
                ->orderBy('created_at')->limit(30)->get(),
            'suspended' => Zumra::query()->whereNotNull('suspended_at')->orderByDesc('suspended_at')->limit(30)->get(),
            'hiddenPosts' => ZumraPost::query()->whereNotNull('hidden_at')->with(['author', 'zumra'])->orderByDesc('hidden_at')->limit(30)->get(),
            'toReviewCount' => ZumraReport::query()->open()->count(),
            'suspendedCount' => Zumra::query()->whereNotNull('suspended_at')->count() + ZumraPost::query()->whereNotNull('hidden_at')->count(),
        ]);
    }

    private function zumra(string $id): Zumra
    {
        Gate::authorize('moderate');
        abort_unless(Str::isUuid($id), 404);

        return Zumra::query()->findOrFail($id);
    }

    private function post(string $id): ZumraPost
    {
        Gate::authorize('moderate');
        abort_unless(Str::isUuid($id), 404);

        return ZumraPost::query()->findOrFail($id);
    }
}
