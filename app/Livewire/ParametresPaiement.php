<?php

namespace App\Livewire;

use App\Exceptions\PaymentException;
use App\Services\GeniusPay;
use App\Services\Payments;
use App\Services\Settings;
use App\Settings\Registry;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Paiement par GeniusPay (Lot 6b, D-169) : le Président saisit ici les clés du prestataire et choisit le mode. Les clés secrètes ne
 * sont jamais réaffichées (champ vide = inchangé) ; chaque changement est inscrit au journal d'administration. Le mode réel est
 * refusé tant que des ZAHAB d'essai subsistent, et le mode d'essai tant que des paiements réels existent.
 */
#[Layout('components.layouts.app', ['narrow' => true])]
#[Title('Paiement')]
class ParametresPaiement extends Component
{
    public string $mode = 'sandbox';

    public string $baseUrl = '';

    public string $apiKey = '';

    /** Saisies de secrets : jamais remplies avec les valeurs enregistrées. */
    public string $apiSecret = '';

    public string $webhookSecret = '';

    public string $rechargeMin = '';

    public string $rechargeMax = '';

    public bool $confirmLive = false;

    public ?string $saved = null;

    public ?string $testResult = null;

    public bool $testFailed = false;

    public function mount(Settings $settings): void
    {
        Gate::authorize('super-admin');

        $this->mode = (string) $settings->get('geniuspay_mode');
        $this->baseUrl = (string) $settings->get('geniuspay_base_url');
        $this->apiKey = (string) $settings->get('geniuspay_api_key');
        $this->rechargeMin = (string) $settings->get('wallet_recharge_min');
        $this->rechargeMax = (string) $settings->get('wallet_recharge_max');
    }

    public function save(Settings $settings, Payments $payments): void
    {
        Gate::authorize('super-admin');
        $this->reset('saved', 'testResult', 'testFailed');
        $this->resetErrorBag();

        $this->validate([
            'mode' => Registry::find('geniuspay_mode')->rules,
            'baseUrl' => Registry::find('geniuspay_base_url')->rules,
            'apiKey' => Registry::find('geniuspay_api_key')->rules,
            'apiSecret' => Registry::find('geniuspay_api_secret')->rules,
            'webhookSecret' => Registry::find('geniuspay_webhook_secret')->rules,
            'rechargeMin' => Registry::find('wallet_recharge_min')->rules,
            'rechargeMax' => ['required', 'integer', 'gte:rechargeMin', 'max:100000000'],
        ], [], [
            'mode' => 'mode', 'baseUrl' => 'adresse de l’API', 'apiKey' => 'clé publique', 'apiSecret' => 'clé secrète',
            'webhookSecret' => 'secret du webhook', 'rechargeMin' => 'recharge minimum', 'rechargeMax' => 'recharge maximum',
        ]);

        $current = (string) $settings->get('geniuspay_mode');

        if ($this->mode !== $current) {
            $this->guardModeChange($payments, $this->mode);
        }

        // Une clé d'un autre mode que celui choisi est refusée tout de suite, pour ne jamais mélanger essai et réel.
        $this->guardKeyMode('apiKey', $this->apiKey, 'pk_');
        $this->guardKeyMode('apiSecret', $this->apiSecret, 'sk_');

        $changes = [
            'geniuspay_mode' => $this->mode,
            'geniuspay_base_url' => $this->baseUrl,
            'geniuspay_api_key' => $this->apiKey === '' ? null : $this->apiKey,
            'wallet_recharge_min' => (int) $this->rechargeMin,
            'wallet_recharge_max' => (int) $this->rechargeMax,
        ];

        foreach (['geniuspay_api_secret' => $this->apiSecret, 'geniuspay_webhook_secret' => $this->webhookSecret] as $key => $value) {
            if ($value !== '') {
                $changes[$key] = $value;
            }
        }

        $settings->update($changes, Auth::user());

        $this->reset('apiSecret', 'webhookSecret', 'confirmLive');
        $this->saved = 'Réglages du paiement enregistrés.';
    }

    public function testConnection(GeniusPay $provider): void
    {
        Gate::authorize('super-admin');
        $this->reset('saved', 'testResult', 'testFailed');

        try {
            $account = $provider->account();
            $this->testResult = 'Connexion réussie : compte « '.($account['name'] ?? 'inconnu').' », mode '.($provider->mode() === 'live' ? 'réel' : 'essai').'.';
        } catch (PaymentException $e) {
            $this->testFailed = true;
            $this->testResult = $provider->configured()
                ? 'Le prestataire n’a pas accepté la connexion : vérifiez les clés et l’adresse de l’API.'
                : 'Les clés ne sont pas complètes pour le mode choisi : enregistrez une clé publique et une clé secrète de ce mode.';
        }
    }

    public function render(Settings $settings, GeniusPay $provider)
    {
        Gate::authorize('super-admin');

        return view('livewire.parametres-paiement', [
            'modes' => Registry::find('geniuspay_mode')->options,
            'hasSecret' => $settings->hasSecret('geniuspay_api_secret'),
            'hasWebhookSecret' => $settings->hasSecret('geniuspay_webhook_secret'),
            'configured' => $provider->configured(),
            'currentMode' => $provider->mode(),
            'webhookUrl' => route('webhooks.geniuspay'),
        ]);
    }

    private function guardModeChange(Payments $payments, string $target): void
    {
        if ($target === 'live') {
            if (! $this->confirmLive) {
                throw ValidationException::withMessages(['confirmLive' => 'Cochez la case pour confirmer le passage au mode réel.']);
            }

            if ($payments->sandboxResidue() > 0) {
                throw ValidationException::withMessages(['mode' => 'Des ZAHAB d’essai sont encore dans des portefeuilles : terminez d’abord les essais (page Paiements).']);
            }

            return;
        }

        if ($payments->hasLivePayments()) {
            throw ValidationException::withMessages(['mode' => 'Des paiements réels existent : le mode d’essai ne peut plus être rétabli.']);
        }
    }

    private function guardKeyMode(string $field, string $value, string $prefix): void
    {
        if ($value !== '' && ! str_starts_with($value, $prefix.$this->mode.'_')) {
            throw ValidationException::withMessages([$field => 'Cette clé n’est pas une clé du mode « '.($this->mode === 'live' ? 'réel' : 'essai').' ».']);
        }
    }
}
