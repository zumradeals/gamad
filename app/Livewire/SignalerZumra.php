<?php

namespace App\Livewire;

use App\Enums\ReportCategory;
use App\Livewire\Concerns\GuardsZumraModule;
use App\Models\Zumra;
use App\Models\ZumraPost;
use App\Models\ZumraReport;
use App\Services\ZumraModeration;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Signaler une ZUMRA, ou un message de sa Discussion (D-215). Une personne connectée et vérifiée ; la modération ne lit que le
 * message signalé. Le signalant n'est jamais montré.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Signaler')]
class SignalerZumra extends Component
{
    use GuardsZumraModule;

    #[Locked]
    public string $zumraId;

    #[Locked]
    public ?string $postId = null;

    public string $category = '';

    public string $details = '';

    public function mount(Zumra $zumra, ?ZumraPost $post = null): void
    {
        abort_if($post && $post->zumra_id !== $zumra->getKey(), 404);
        Gate::authorize('view', $zumra);
        $post ? Gate::authorize('reportPost', [$zumra, $post]) : Gate::authorize('report', $zumra);

        $this->zumraId = $zumra->id;
        $this->postId = $post?->id;
    }

    public function send(ZumraModeration $moderation)
    {
        $post = $this->post();
        $post ? $moderation->reportPost(Auth::user(), $post, $this->category, $this->details)
            : $moderation->reportZumra(Auth::user(), $this->zumra(), $this->category, $this->details);

        session()->flash('status', 'Merci. Un modérateur examinera votre signalement.');

        return $this->redirect(route('zumras.show', $post ? ['zumra' => $this->zumraId, 'onglet' => 'discussion'] : $this->zumraId), navigate: false);
    }

    public function render()
    {
        $zumra = $this->zumra();
        $post = $this->post();

        return view('livewire.signaler-zumra', [
            'zumra' => $zumra,
            'post' => $post?->load('author'),
            'categories' => ReportCategory::cases(),
            'already' => ZumraReport::query()->where('zumra_id', $zumra->id)->where('reporter_id', Auth::id())
                ->when($post, fn ($q) => $q->where('zumra_post_id', $post->id), fn ($q) => $q->whereNull('zumra_post_id'))->exists(),
        ]);
    }

    private function zumra(): Zumra
    {
        return Zumra::query()->findOrFail($this->zumraId);
    }

    private function post(): ?ZumraPost
    {
        return $this->postId ? ZumraPost::query()->where('zumra_id', $this->zumraId)->findOrFail($this->postId) : null;
    }
}
