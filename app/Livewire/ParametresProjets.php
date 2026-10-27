<?php

namespace App\Livewire;

use App\Services\Settings;
use App\Settings\Registry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Module Projets (D-213) : le super administrateur le fait suivre le module ZUMRA (défaut), le désactive, l'ouvre aux testeurs des ZUMRA ou le
 * rend public. Chaque changement est inscrit au journal d'administration. Aucune donnée n'est supprimée, aucun projet existant n'est modifié.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Module Projets')]
class ParametresProjets extends Component
{
    public string $state = 'inherit';

    public ?string $saved = null;

    public function mount(Settings $settings): void
    {
        Gate::authorize('super-admin');
        $this->state = (string) $settings->get('projets_module');
    }

    public function save(Settings $settings): void
    {
        Gate::authorize('super-admin');
        $this->reset('saved');
        $this->resetErrorBag();
        $this->validate(['state' => Registry::find('projets_module')->rules], [], ['state' => 'état du module']);

        $settings->update(['projets_module' => $this->state], Auth::user());
        $this->saved = 'Réglage du module Projets enregistré.';
    }

    public function render()
    {
        Gate::authorize('super-admin');

        return view('livewire.parametres-projets', ['states' => Registry::find('projets_module')->options]);
    }
}
