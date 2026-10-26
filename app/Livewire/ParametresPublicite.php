<?php

namespace App\Livewire;

use App\Services\Settings;
use App\Settings\Registry;
use App\Support\PubliciteModule;
use App\Support\ZumraModule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Service GAMAD Publicité (D-209, D-210) : le super administrateur le désactive, l'ouvre à des testeurs ou le rend public. Chaque
 * changement est inscrit au journal d'administration. Cette page n'active aucune diffusion payante.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Service GAMAD Publicité')]
class ParametresPublicite extends Component
{
    public string $state = PubliciteModule::OFF;

    public string $testers = '';

    public string $diffusion = 'closed';

    public string $minAudience = '100';

    public bool $paymentConfirmed = false;

    public ?string $saved = null;

    public function mount(Settings $settings): void
    {
        Gate::authorize('super-admin');

        $this->state = (string) $settings->get('publicite_module');
        $this->testers = (string) ($settings->get('publicite_testers') ?? '');
        $this->diffusion = (string) $settings->get('publicite_diffusion');
        $this->minAudience = (string) $settings->get('publicite_min_audience');
    }

    public function save(Settings $settings): void
    {
        Gate::authorize('super-admin');
        $this->reset('saved');
        $this->resetErrorBag();

        $this->validate([
            'state' => Registry::find('publicite_module')->rules,
            'diffusion' => Registry::find('publicite_diffusion')->rules,
            'minAudience' => Registry::find('publicite_min_audience')->rules,
            'testers' => [...Registry::find('publicite_testers')->rules, function (string $attribute, mixed $value, \Closure $fail) {
                foreach (preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $email) {
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail('« '.mb_substr($email, 0, 60).' » n’est pas une adresse e-mail valide.');
                    }
                }
            }],
        ], [], ['state' => 'état du service', 'testers' => 'comptes testeurs', 'diffusion' => 'diffusion payante', 'minAudience' => 'audience minimale']);

        // La diffusion payante ne s'ouvre que par une confirmation écrite que le dispositif d'encaissement est validé (D-212).
        if ($this->diffusion === 'open' && (string) $settings->get('publicite_diffusion') !== 'open' && ! $this->paymentConfirmed) {
            $this->addError('paymentConfirmed', 'Confirmez que le dispositif d’encaissement est validé avant d’ouvrir la diffusion payante.');

            return;
        }

        $settings->update(['publicite_module' => $this->state, 'publicite_testers' => implode("\n", ZumraModule::parse($this->testers)), 'publicite_diffusion' => $this->diffusion, 'publicite_min_audience' => (int) $this->minAudience], Auth::user());
        $this->paymentConfirmed = false;
        $this->testers = implode("\n", PubliciteModule::testers());
        $this->saved = 'Réglages du service GAMAD Publicité enregistrés.';
    }

    public function render()
    {
        Gate::authorize('super-admin');

        return view('livewire.parametres-publicite', ['states' => Registry::find('publicite_module')->options]);
    }
}
