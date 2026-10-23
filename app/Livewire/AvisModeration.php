<?php

namespace App\Livewire;

use App\Services\PersonModeration;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** L'avis de la modération vu par la personne visée : le motif, le délai, et son droit de s'expliquer avant toute suspension (D-145). */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Avis de la modération')]
class AvisModeration extends Component
{
    public string $explanation = '';

    public function explain(PersonModeration $moderation): void
    {
        $moderation->explain(Auth::user(), $this->explanation);

        session()->flash('status', 'Votre explication est envoyée à la modération.');
    }

    public function render()
    {
        $notice = Auth::user()->openNotice();

        if ($this->explanation === '' && $notice?->isExplained()) {
            $this->explanation = (string) $notice->explanation;
        }

        return view('livewire.avis-moderation', ['notice' => $notice?->load('issuer')]);
    }
}
