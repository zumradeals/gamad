<?php

namespace App\Livewire;

use App\Services\Settings;
use App\Settings\Registry;
use App\Support\ZumraModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Module ZUMRA (Z1, D-205) : le super administrateur le désactive, l'ouvre à des testeurs ou le rend public. Chaque changement est
 * inscrit au journal d'administration. Rien d'autre que cette page ne change l'état du module.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Module ZUMRA')]
class ParametresZumra extends Component
{
    public string $state = ZumraModule::OFF;

    public string $testers = '';

    public ?string $saved = null;

    public function mount(Settings $settings): void
    {
        Gate::authorize('super-admin');

        $this->state = (string) $settings->get('zumra_module');
        $this->testers = (string) ($settings->get('zumra_testers') ?? '');
    }

    public function save(Settings $settings): void
    {
        Gate::authorize('super-admin');
        $this->reset('saved');
        $this->resetErrorBag();

        $this->validate([
            'state' => Registry::find('zumra_module')->rules,
            'testers' => [...Registry::find('zumra_testers')->rules, function (string $attribute, mixed $value, \Closure $fail) {
                foreach (preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $email) {
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail('« '.mb_substr($email, 0, 60).' » n’est pas une adresse e-mail valide.');
                    }
                }
            }],
        ], [], ['state' => 'état du module', 'testers' => 'comptes testeurs']);

        $settings->update(['zumra_module' => $this->state, 'zumra_testers' => implode("\n", ZumraModule::parse($this->testers))], Auth::user());
        $this->testers = implode("\n", ZumraModule::testers());
        $this->saved = 'Réglages du module ZUMRA enregistrés.';
    }

    public function render()
    {
        Gate::authorize('super-admin');

        return view('livewire.parametres-zumra', ['states' => Registry::find('zumra_module')->options]);
    }
}
