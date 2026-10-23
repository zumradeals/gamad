<?php

namespace App\Livewire;

use App\Models\UserNotification;
use App\Services\Notifier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Notifications dans l'application (écran 15) : les 50 dernières, « Tout marquer lu ». Ni e-mail ni SMS à ce stade. */
#[Layout('components.layouts.app')]
#[Title('Notifications')]
class PageNotifications extends Component
{
    public const SHOWN = 50;

    public function markAllRead(Notifier $notifier): void
    {
        $notifier->readAll(Auth::user());
    }

    public function render(Notifier $notifier)
    {
        $user = Auth::user();

        return view('livewire.page-notifications', [
            'notifications' => UserNotification::query()
                ->where('user_id', $user->getKey())
                ->with(['actor', 'publication', 'zumra'])
                ->orderByDesc('created_at')->orderByDesc('id')
                ->limit(self::SHOWN)->get(),
            'unread' => $notifier->unreadCount($user),
        ]);
    }
}
