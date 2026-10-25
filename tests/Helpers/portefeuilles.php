<?php

use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payments;
use App\Services\Settings;
use App\Services\Wallets;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;

// Aides communes aux tests des portefeuilles, des paiements et des contributions (Lot 6).

function ouvrirPortefeuilles(): void
{
    app(Settings::class)->update(['wallet_enabled' => '1'], User::factory()->superAdmin()->create());
}

function portefeuilles(): Wallets
{
    return app(Wallets::class);
}

const GP_BASE = 'https://geniuspay.ci/api/v1/merchant';
const WHSEC = 'whsec_secretdetest';

/** Ouvre les portefeuilles et renseigne les clés (par défaut en mode d'essai). */
function configurerPaiement(string $mode = 'sandbox'): User
{
    $admin = User::factory()->superAdmin()->create();
    app(Settings::class)->update([
        'wallet_enabled' => '1',
        'geniuspay_mode' => $mode,
        'geniuspay_api_key' => "pk_{$mode}_abc123",
        'geniuspay_api_secret' => "sk_{$mode}_def456",
        'geniuspay_webhook_secret' => WHSEC,
    ], $admin);

    return $admin;
}

/**
 * Prestataire simulé : la création rend la référence MTX-n ; la lecture d'un paiement rend l'état demandé par référence.
 *
 * @param  array<string, array<string, mixed>>  $etats  référence => champs remplaçant ceux de la réponse
 */
function prestataire(array $etats = [], string $env = 'sandbox'): void
{
    static $n = 0;

    faux(function (Request $request) use (&$n, $etats, $env) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($request->method() === 'POST' && str_ends_with($path, '/payments')) {
            $n++;

            return Http::response(['success' => true, 'data' => ['reference' => 'MTX-'.$n, 'checkout_url' => 'https://geniuspay.ci/checkout/MTX-'.$n, 'environment' => $env, 'status' => 'pending']], 201);
        }

        if ($request->method() === 'GET' && str_ends_with($path, '/account')) {
            return Http::response(['success' => true, 'data' => ['name' => 'GAMAD test', 'status' => 'active']]);
        }

        if ($request->method() === 'GET' && preg_match('#/payments/(.+)$#', $path, $m)) {
            $ref = urldecode($m[1]);
            $etat = $etats[$ref] ?? [];

            return $etat === ['_down' => true]
                ? Http::response(['success' => false], 500)
                : Http::response(['success' => true, 'data' => $etat + ['reference' => $ref, 'status' => 'pending', 'amount' => Payment::query()->where('provider_reference', $ref)->value('amount'), 'environment' => $env]]);
        }

        return Http::response([], 404);
    });
}

/** Remplace le prestataire simulé : un nouvel Http::fake s'ajouterait aux précédents, dont le premier qui correspond répond. */
function faux(mixed $reponses): void
{
    Http::swap(new Factory);
    Http::fake($reponses);
}

function lancer(User $qui, int $montant, ?Wallet $wallet = null): Payment
{
    $wallet ??= app(Wallets::class)->forPerson($qui);

    return app(Payments::class)->start($qui, $wallet, $montant, route('portefeuille'), route('portefeuille'));
}

/** Un webhook signé comme le fait GeniusPay. */
function webhook(Payment $p, string $event = 'payment.success', array $options = [])
{
    $body = json_encode(['event' => $event, 'timestamp' => now()->toIso8601String(), 'data' => ['transaction' => ['reference' => $options['reference'] ?? $p->provider_reference, 'amount' => $p->amount, 'status' => 'completed', 'metadata' => ['payment_id' => $p->id]], 'environment' => 'sandbox']]);
    $ts = (string) ($options['timestamp'] ?? time());
    $sig = $options['signature'] ?? hash_hmac('sha256', $ts.'.'.$body, $options['secret'] ?? WHSEC);

    return test()->call('POST', route('webhooks.geniuspay'), [], [], [], [
        'HTTP_X_WEBHOOK_SIGNATURE' => $sig, 'HTTP_X_WEBHOOK_TIMESTAMP' => $ts, 'HTTP_X_WEBHOOK_EVENT' => $event,
        'HTTP_X_WEBHOOK_ENVIRONMENT' => $options['env'] ?? 'sandbox', 'CONTENT_TYPE' => 'application/json',
    ], $body);
}

function solde(Wallet $w): int
{
    return app(Wallets::class)->balance($w);
}

/** Le texte de la zone principale d'une page, sans l'en-tête : le menu utilisateur y affiche le nom de la personne connectée. */
function contenuPrincipal(TestResponse $reponse): string
{
    preg_match('/<main.*?<\/main>/s', $reponse->getContent(), $main);

    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($main[0] ?? ''))));
}
