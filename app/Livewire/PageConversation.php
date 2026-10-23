<?php

namespace App\Livewire;

use App\Models\Conversation;
use App\Services\Conversations;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Conversation privée (écran 12), ouverte par un geste accepté. Rafraîchie toutes les 10 secondes (wire:poll), sans WebSocket.
 * Seuls les deux participants la voient : pour toute autre personne, elle n'existe pas (404).
 */
#[Layout('components.layouts.app')]
class PageConversation extends Component
{
    public const POLL_SECONDS = 10;

    public const SHOWN = 200;

    /** Verrouillé ; et le droit de voir est revérifié à chaque affichage et à chaque envoi. */
    #[Locked]
    public string $conversationId;

    public string $body = '';

    public function mount(Conversation $conversation): void
    {
        Gate::authorize('view', $conversation);

        $this->conversationId = $conversation->id;
    }

    public function send(Conversations $conversations): void
    {
        $conversations->send(Auth::user(), $this->conversation(), $this->body);

        $this->reset('body');
        $this->resetErrorBag();
        $this->dispatch('message-sent');
    }

    public function render(Conversations $conversations)
    {
        $viewer = Auth::user();
        $conversation = $this->conversation()->load(['owner.profile', 'helper.profile', 'publication']);

        // Ouvrir la conversation, c'est la lire (et seulement s'il y a du nouveau : pas d'écriture à chaque rafraîchissement).
        $conversations->markRead($viewer, $conversation);

        $messages = $conversation->messages()->with('sender')->orderByDesc('created_at')->orderByDesc('id')->limit(self::SHOWN)->get()->reverse()->values();
        $other = $conversation->otherPerson($viewer);

        return view('livewire.page-conversation', [
            'conversation' => $conversation,
            'viewer' => $viewer,
            'other' => $other,
            'otherProfile' => $other?->profile,
            'messages' => $messages,
            'canSend' => Gate::forUser($viewer)->allows('send', $conversation),
        ])->title($other ? 'Conversation avec '.$other->name : 'Conversation');
    }

    private function conversation(): Conversation
    {
        $conversation = Conversation::query()->findOrFail($this->conversationId);

        Gate::authorize('view', $conversation);

        return $conversation;
    }
}
