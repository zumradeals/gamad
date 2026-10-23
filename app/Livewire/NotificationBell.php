<?php

namespace App\Livewire;

use App\Services\Notifier;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/** La cloche de la barre du haut : un seul chiffre, le nombre de notifications non lues. Elle se met à jour toute seule. */
class NotificationBell extends Component
{
    public function render(Notifier $notifier)
    {
        $user = Auth::user();

        return view('livewire.notification-bell', ['count' => $user ? $notifier->unreadCount($user) : 0]);
    }
}
