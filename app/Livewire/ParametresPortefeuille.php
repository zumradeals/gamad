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
 * Portefeuilles ZAHAB (Lot 6, D-172) : le Président ouvre ou ferme les portefeuilles et fixe les plafonds de solde. Réservé au super
 * administrateur ; chaque changement est inscrit au journal d'administration.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Portefeuilles')]
class ParametresPortefeuille extends Component
{
    public string $enabled = '0';

    public string $maxPerson = '';

    public string $maxZumra = '';

    public string $contributionMin = '';

    public string $engagementDays = '';

    public string $validationThreshold = '';

    public ?string $saved = null;

    public function mount(Settings $settings): void
    {
        Gate::authorize('super-admin');

        $this->enabled = (string) $settings->get('wallet_enabled');
        $this->maxPerson = (string) $settings->get('wallet_max_balance_person');
        $this->maxZumra = (string) $settings->get('wallet_max_balance_zumra');
        $this->contributionMin = (string) $settings->get('contribution_min');
        $this->engagementDays = (string) $settings->get('contribution_engagement_days');
        $this->validationThreshold = (string) $settings->get('wallet_zumra_validation_threshold');
    }

    public function save(Settings $settings): void
    {
        Gate::authorize('super-admin');
        $this->reset('saved');
        $this->resetErrorBag();

        $this->validate([
            'enabled' => Registry::find('wallet_enabled')->rules,
            'maxPerson' => Registry::find('wallet_max_balance_person')->rules,
            'maxZumra' => Registry::find('wallet_max_balance_zumra')->rules,
            'contributionMin' => Registry::find('contribution_min')->rules,
            'engagementDays' => Registry::find('contribution_engagement_days')->rules,
            'validationThreshold' => Registry::find('wallet_zumra_validation_threshold')->rules,
        ], [], ['enabled' => 'état des portefeuilles', 'maxPerson' => 'solde maximum d’une personne', 'maxZumra' => 'solde maximum d’une ZUMRA', 'contributionMin' => 'contribution minimum', 'engagementDays' => 'durée d’engagement', 'validationThreshold' => 'seuil de validation']);

        $settings->update([
            'wallet_enabled' => $this->enabled,
            'wallet_max_balance_person' => (int) $this->maxPerson,
            'wallet_max_balance_zumra' => (int) $this->maxZumra,
            'contribution_min' => (int) $this->contributionMin,
            'contribution_engagement_days' => (int) $this->engagementDays,
            'wallet_zumra_validation_threshold' => (int) $this->validationThreshold,
        ], Auth::user());

        $this->saved = 'Réglages des portefeuilles enregistrés.';
    }

    public function render()
    {
        Gate::authorize('super-admin');

        return view('livewire.parametres-portefeuille', ['states' => Registry::find('wallet_enabled')->options]);
    }
}
