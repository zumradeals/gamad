<?php

namespace App\Livewire;

use App\Support\PubliciteModule;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Le consentement publicitaire (D-211), dans Mon espace › Paramètres : distinct du matching et de la découverte par les entreprises,
 * désactivé par défaut, révocable à tout moment. Sans lui, une personne n'est jamais comptée ni ciblée par une annonce personnalisée.
 * N'existe que lorsque le service GAMAD Publicité est ouvert à la personne.
 */
class ConsentementPublicite extends Component
{
    public bool $enabled = false;

    public ?string $saved = null;

    public function mount(): void
    {
        $this->enabled = (bool) Auth::user()->profile?->ads_personalization;
    }

    public function updatedEnabled(): void
    {
        abort_unless(PubliciteModule::accessibleTo(Auth::user()), 404);
        $profile = Auth::user()->profile;
        abort_if($profile === null, 404);

        $profile->forceFill(['ads_personalization' => $this->enabled, 'ads_personalization_at' => $this->enabled ? now() : null])->save();
        $this->saved = $this->enabled ? 'Annonces personnalisées activées.' : 'Annonces personnalisées désactivées : vous n’êtes plus ciblé(e).';
    }

    public function render()
    {
        return PubliciteModule::accessibleTo(Auth::user()) && Auth::user()->profile ? view('livewire.consentement-publicite') : '<div></div>';
    }
}
