<?php

namespace App\Livewire\Concerns;

use App\Support\ContributionsModule;
use Illuminate\Support\Facades\Auth;

/**
 * Les pages du module Contributions n'existent pas (404) pour qui n'y a pas accès. Le contrôle s'exécute à chaque requête Livewire, pas
 * seulement au chargement de la page : une action appelée directement est refusée de la même façon (C1, D-207).
 */
trait GuardsContributionsModule
{
    public function bootGuardsContributionsModule(): void
    {
        abort_unless(ContributionsModule::accessibleTo(Auth::user()), 404);
    }
}
