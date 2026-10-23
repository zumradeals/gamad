<?php

namespace App\Http\Controllers;

use App\Exceptions\PaymentException;
use App\Models\Payment;
use App\Services\GeniusPay;
use App\Services\Payments;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Rappel serveur de GeniusPay (D-169). Le corps n'est jamais cru : sa signature est vérifiée, il ne sert qu'à retrouver le
 * paiement, dont l'état réel est ensuite demandé au prestataire. Un webhook rejoué ne crédite rien de plus.
 */
class GeniusPayWebhookController extends Controller
{
    public function handle(Request $request, GeniusPay $provider, Payments $payments): JsonResponse
    {
        $body = $request->getContent();

        if (! $provider->verifySignature($body, $request->header('X-Webhook-Timestamp'), $request->header('X-Webhook-Signature'))) {
            return response()->json(['received' => false], 401);
        }

        // Un événement d'un autre mode que celui configuré n'est pas pour nous.
        if ($request->header('X-Webhook-Environment') !== $provider->mode()) {
            return response()->json(['received' => true, 'ignored' => 'environment']);
        }

        $transaction = json_decode($body, true)['data']['transaction'] ?? null;
        $payment = is_array($transaction) ? $this->find($transaction) : null;

        if (! $payment) {
            return response()->json(['received' => true, 'ignored' => 'unknown']);
        }

        try {
            $payments->reconcile($payment);
        } catch (PaymentException) {
            // Le prestataire ne répond pas : une erreur lui fait renvoyer la notification plus tard.
            return response()->json(['received' => false], 503);
        }

        return response()->json(['received' => true]);
    }

    /** @param  array<string, mixed>  $transaction */
    private function find(array $transaction): ?Payment
    {
        $reference = $transaction['reference'] ?? null;
        $id = $transaction['metadata']['payment_id'] ?? null;

        if (is_string($reference) && $reference !== '' && ($payment = Payment::query()->where('provider_reference', $reference)->first())) {
            return $payment;
        }

        return is_string($id) && Str::isUuid($id) ? Payment::query()->find($id) : null;
    }
}
