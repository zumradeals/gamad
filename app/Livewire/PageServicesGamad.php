<?php

namespace App\Livewire;

use App\Services\ServicesGamad;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * « Services GAMAD » (D-209), depuis Mon espace, groupe Gestion : la porte d'entrée unique vers les départements numériques. Elle ne montre
 * que les services disponibles et ouverts à la personne ; sans aucun service, la page n'existe pas (404), comme l'entrée de menu.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Services GAMAD')]
class PageServicesGamad extends Component
{
    public function boot(): void
    {
        abort_if(ServicesGamad::availableFor(Auth::user()) === [], 404);
    }

    public function render()
    {
        return view('livewire.page-services-gamad', ['services' => ServicesGamad::availableFor(Auth::user())]);
    }
}
