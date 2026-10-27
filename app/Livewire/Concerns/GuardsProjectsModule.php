<?php

namespace App\Livewire\Concerns;

use App\Support\ProjectsModule;
use Illuminate\Support\Facades\Auth;

/** Les pages Projets n'existent pas (404) pour qui n'y a pas accès, à chaque requête Livewire (D-213). */
trait GuardsProjectsModule
{
    public function bootGuardsProjectsModule(): void
    {
        abort_unless(ProjectsModule::accessibleTo(Auth::user()), 404);
    }
}
