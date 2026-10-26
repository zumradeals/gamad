<?php

namespace App\Livewire;

use App\Models\ContactMessage;
use App\Services\ContactMessages;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Les messages de contact (D-208), pour le seul super administrateur : ils contiennent des données personnelles. Ouvrir un
 * message le passe à « Lu » ; chaque lecture, chaque message marqué « Traité » et chaque suppression sont au journal.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Messages de contact')]
class MessagesContact extends Component
{
    use WithPagination;

    private const PER_PAGE = 15;

    #[Url(as: 'etat')]
    public string $state = '';

    public ?string $openId = null;

    public function mount(): void
    {
        Gate::authorize('super-admin');
    }

    public function updatedState(): void
    {
        $this->resetPage();
        $this->openId = null;
    }

    public function open(ContactMessages $messages, string $id): void
    {
        Gate::authorize('super-admin');
        $message = ContactMessage::query()->findOrFail($id);
        $messages->open(Auth::user(), $message);
        $this->openId = $message->getKey();
    }

    public function close(): void
    {
        $this->openId = null;
    }

    public function handle(ContactMessages $messages, string $id): void
    {
        Gate::authorize('super-admin');
        $messages->markHandled(Auth::user(), ContactMessage::query()->findOrFail($id));
    }

    public function delete(ContactMessages $messages, string $id): void
    {
        Gate::authorize('super-admin');
        $messages->delete(Auth::user(), ContactMessage::query()->findOrFail($id));
        $this->openId = null;
    }

    public function render()
    {
        Gate::authorize('super-admin');

        $query = ContactMessage::query()->orderByDesc('created_at')->orderByDesc('id');
        if (array_key_exists($this->state, ContactMessage::STATES)) {
            $query->where('state', $this->state);
        }

        return view('livewire.messages-contact', [
            'items' => $query->paginate(self::PER_PAGE),
            'opened' => $this->openId ? ContactMessage::query()->find($this->openId) : null,
            'newCount' => app(ContactMessages::class)->unreadCount(),
        ]);
    }
}
