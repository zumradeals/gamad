<?php

namespace App\Livewire\Concerns;

use App\Support\PubliciteModule;
use Illuminate\Support\Facades\Auth;

/**
 * Les pages de GAMAD Publicité n'existent pas (404) pour qui n'y a pas accès. Le contrôle s'exécute à chaque requête Livewire, pas
 * seulement au chargement de la page : une action appelée directement est refusée de la même façon (D-209, D-212).
 */
trait GuardsPubliciteModule
{
    public function bootGuardsPubliciteModule(): void
    {
        abort_unless(PubliciteModule::accessibleTo(Auth::user()), 404);
    }
}
