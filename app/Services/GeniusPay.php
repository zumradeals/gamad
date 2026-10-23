<?php

namespace App\Services;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Client de l'API marchand GeniusPay (D-169). Les clés viennent d'Espace → Paramètres ; rien n'est lu ailleurs. Ce client ne décide
 * de rien : il parle au prestataire, et Payments applique les règles.
 */
class GeniusPay
{
    /** Écart toléré, en secondes, entre l'horodatage d'un webhook et l'heure du serveur. */
    public const TOLERANCE = 300;

    public function __construct(private readonly Settings $settings) {}

    /** « sandbox » ou « live ». */
    public function mode(): string
    {
        return (string) $this->settings->get('geniuspay_mode');
    }

    /** Clés complètes et du bon mode : une clé d'essai ne sert jamais en mode réel, et inversement. */
    public function configured(): bool
    {
        $key = (string) $this->settings->get('geniuspay_api_key');

        return $key !== ''
            && $this->settings->hasSecret('geniuspay_api_secret')
            && str_starts_with($key, 'pk_'.$this->mode().'_')
            && str_starts_with((string) $this->settings->secret('geniuspay_api_secret'), 'sk_'.$this->mode().'_');
    }

    public function webhookConfigured(): bool
    {
        return $this->settings->hasSecret('geniuspay_webhook_secret');
    }

    /**
     * Crée le paiement chez le prestataire. Sans moyen de paiement imposé : la personne choisit sur la page hébergée. Aucune
     * information personnelle n'est transmise.
     *
     * @return array{reference: string, checkout_url: string}
     */
    public function create(Payment $payment, string $description, string $successUrl, string $errorUrl): array
    {
        $data = $this->call('post', '/payments', [
            'amount' => $payment->amount,
            'currency' => 'XOF',
            'description' => $description,
            'external_reference' => $payment->getKey(),
            'success_url' => $successUrl,
            'error_url' => $errorUrl,
            'metadata' => ['payment_id' => $payment->getKey()],
        ], ['Idempotency-Key' => $payment->getKey()]);

        $reference = $data['reference'] ?? null;
        $url = $data['checkout_url'] ?? null;

        if (! is_string($reference) || $reference === '' || ! is_string($url) || ! str_starts_with($url, 'https://') || ($data['environment'] ?? $this->mode()) !== $this->mode()) {
            throw PaymentException::provider();
        }

        return ['reference' => $reference, 'checkout_url' => $url];
    }

    /**
     * L'état réel d'un paiement, lu chez le prestataire. C'est la seule source qui crédite un portefeuille.
     *
     * @return array<string, mixed>
     */
    public function fetch(string $reference): array
    {
        return $this->call('get', '/payments/'.rawurlencode($reference));
    }

    /**
     * Le compte marchand (essai de connexion).
     *
     * @return array<string, mixed>
     */
    public function account(): array
    {
        return $this->call('get', '/account');
    }

    /** Signature HMAC-SHA256 de « horodatage.corps brut », comparée à temps constant, avec un horodatage récent. */
    public function verifySignature(string $payload, ?string $timestamp, ?string $signature): bool
    {
        $secret = $this->webhookConfigured() ? $this->settings->secret('geniuspay_webhook_secret') : null;

        if ($secret === null || $timestamp === null || $signature === null || ! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::TOLERANCE) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$payload, $secret), $signature);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $extraHeaders
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, array $body = [], array $extraHeaders = []): array
    {
        if (! $this->configured()) {
            throw PaymentException::unavailable();
        }

        try {
            $response = Http::withHeaders([
                'X-API-Key' => (string) $this->settings->get('geniuspay_api_key'),
                'X-API-Secret' => (string) $this->settings->secret('geniuspay_api_secret'),
            ] + $extraHeaders)->acceptJson()->timeout(15)->{$method}(rtrim((string) $this->settings->get('geniuspay_base_url'), '/').$path, $body);
        } catch (Throwable) {
            throw PaymentException::provider();
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || ($json['success'] ?? false) !== true || ! is_array($json['data'] ?? null)) {
            report(new \RuntimeException('GeniusPay '.$method.' '.Str::before($path, '?').' : HTTP '.$response->status().' '.Str::limit((string) ($json['error']['code'] ?? $json['code'] ?? ''), 60)));

            throw PaymentException::provider();
        }

        return $json['data'];
    }
}
