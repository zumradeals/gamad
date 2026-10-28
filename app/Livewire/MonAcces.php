<?php

namespace App\Livewire;

use App\Actions\Fortify\PasswordValidationRules;
use App\Core\Identity\Credentials;
use App\Core\Identity\Sessions;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * « Mon accès », dans Mon espace › Paramètres : changer son mot de passe, voir où l'on est connecté(e) et fermer les sessions
 * qu'on ne reconnaît pas. Chacun ne voit et ne modifie que son propre accès ; le mot de passe actuel est exigé pour toute
 * opération qui touche aux autres appareils.
 */
class MonAcces extends Component
{
    use PasswordValidationRules;

    private const MAX_ATTEMPTS = 5;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $confirm_password = '';

    public ?string $status = null;

    public function changePassword(Credentials $credentials): void
    {
        $this->reset('status');
        $this->resetErrorBag();
        $this->checkCurrent($credentials, $this->current_password, 'current_password');

        $this->validate(['password' => [...$this->passwordRules(), 'different:current_password']], [], ['password' => 'nouveau mot de passe']);

        $revoked = $credentials->change(Auth::user(), $this->password, session()->getId());

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->status = $revoked > 0
            ? 'Mot de passe modifié. '.$revoked.' autre(s) appareil(s) ont été déconnecté(s).'
            : 'Mot de passe modifié.';
    }

    public function revoke(Sessions $sessions, string $handle): void
    {
        $this->resetErrorBag();
        $this->status = $sessions->revoke(Auth::user(), $handle, session()->getId())
            ? 'Appareil déconnecté.'
            : 'Cet appareil n’est plus connecté.';
    }

    public function revokeOthers(Credentials $credentials, Sessions $sessions): void
    {
        $this->reset('status');
        $this->resetErrorBag();
        $this->checkCurrent($credentials, $this->confirm_password, 'confirm_password');

        $count = $sessions->revokeOthers(Auth::user(), session()->getId());
        $this->reset('confirm_password');
        $this->status = $count > 0 ? $count.' autre(s) appareil(s) déconnecté(s).' : 'Aucun autre appareil n’était connecté.';
    }

    public function render(Sessions $sessions)
    {
        return view('livewire.mon-acces', [
            'supported' => $sessions->supported(),
            'sessions' => $sessions->for(Auth::user(), session()->getId()),
        ]);
    }

    /** Mot de passe actuel exigé, avec un plafond de tentatives : cet écran ne doit pas servir à deviner un mot de passe. */
    private function checkCurrent(Credentials $credentials, string $given, string $field): void
    {
        $key = 'mon-acces:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([$field => 'Trop de tentatives. Réessayez dans '.ceil(RateLimiter::availableIn($key) / 60).' minute(s).']);
        }

        if (! $credentials->verify(Auth::user(), $given)) {
            RateLimiter::hit($key, 60);

            throw ValidationException::withMessages([$field => __('auth.password')]);
        }

        RateLimiter::clear($key);
    }
}
