<?php

namespace App\Livewire\Concerns;

use App\Support\ZumraModule;
use Illuminate\Support\Facades\Auth;

/**
 * Les pages du module ZUMRA n'existent pas (404) pour qui n'y a pas accès. Le contrôle s'exécute à chaque requête Livewire, pas
 * seulement au chargement de la page : une action appelée directement est refusée de la même façon (Z1, D-205).
 */
trait GuardsZumraModule
{
    public function bootGuardsZumraModule(): void
    {
        abort_unless(ZumraModule::accessibleTo(Auth::user()), 404);
    }
}
