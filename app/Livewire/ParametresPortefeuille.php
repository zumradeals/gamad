<?php

namespace App\Livewire;

use App\Services\Settings;
use App\Settings\Registry;
use App\Support\WalletModule;
use App\Support\ZumraModule;
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

    /** Service Zahab Wallet (D-218) : désactivé, test privé ou public ; testeurs ; reversement minimum. */
    public string $moduleState = 'off';

    public string $testers = '';

    public string $payoutMin = '';

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
        $this->moduleState = (string) $settings->get('wallet_module');
        $this->testers = (string) ($settings->get('wallet_testers') ?? '');
        $this->payoutMin = (string) $settings->get('wallet_payout_min');
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
            'moduleState' => Registry::find('wallet_module')->rules,
            'payoutMin' => Registry::find('wallet_payout_min')->rules,
            'testers' => [...Registry::find('wallet_testers')->rules, function (string $attribute, mixed $value, \Closure $fail) {
                foreach (preg_split('/[\s,;]+/', (string) $value, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $email) {
                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail('« '.mb_substr($email, 0, 60).' » n’est pas une adresse e-mail valide.');
                    }
                }
            }],
            'maxPerson' => Registry::find('wallet_max_balance_person')->rules,
            'maxZumra' => Registry::find('wallet_max_balance_zumra')->rules,
            'contributionMin' => Registry::find('contribution_min')->rules,
            'engagementDays' => Registry::find('contribution_engagement_days')->rules,
            'validationThreshold' => Registry::find('wallet_zumra_validation_threshold')->rules,
        ], [], ['moduleState' => 'état du service Zahab Wallet', 'testers' => 'comptes testeurs', 'payoutMin' => 'reversement minimum', 'enabled' => 'état des portefeuilles', 'maxPerson' => 'solde maximum d’une personne', 'maxZumra' => 'solde maximum d’une ZUMRA', 'contributionMin' => 'contribution minimum', 'engagementDays' => 'durée d’engagement', 'validationThreshold' => 'seuil de validation']);

        $settings->update([
            'wallet_enabled' => $this->enabled,
            'wallet_module' => $this->moduleState,
            'wallet_testers' => implode("\n", ZumraModule::parse($this->testers)),
            'wallet_payout_min' => (int) $this->payoutMin,
            'wallet_max_balance_person' => (int) $this->maxPerson,
            'wallet_max_balance_zumra' => (int) $this->maxZumra,
            'contribution_min' => (int) $this->contributionMin,
            'contribution_engagement_days' => (int) $this->engagementDays,
            'wallet_zumra_validation_threshold' => (int) $this->validationThreshold,
        ], Auth::user());

        $this->testers = implode("\n", WalletModule::testers());
        $this->saved = 'Réglages des portefeuilles enregistrés.';
    }

    public function render()
    {
        Gate::authorize('super-admin');

        return view('livewire.parametres-portefeuille', ['states' => Registry::find('wallet_enabled')->options, 'moduleStates' => Registry::find('wallet_module')->options]);
    }
}
