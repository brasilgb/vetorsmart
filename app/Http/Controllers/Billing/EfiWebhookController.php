<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\BillingSetting;
use App\Services\Billing\BillingWebhookService;
use App\Services\Billing\Efi\EfiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Efí notices. The secret in the address authenticates the origin (compared in constant time); the content is
 * confirmed by querying Efí before anything changes. Answers carry no internal data.
 */
class EfiWebhookController extends Controller
{
    public function __construct(private BillingWebhookService $webhooks) {}

    /**
     * Pix: Efí appends /pix to the registered address and posts {"pix": [...]}.
     */
    public function pix(Request $request, string $secret): JsonResponse
    {
        $this->authenticate($secret);

        $result = $this->webhooks->handlePix((array) $request->input('pix', []));

        return response()->json(['status' => 'ok', ...$result]);
    }

    /**
     * Cobranças: Efí posts only a `notification` token. When the events cannot be read, the answer is 503 so Efí
     * notifies again; the open charges are also reconciled by the daily routine.
     */
    public function charges(Request $request, string $secret): JsonResponse
    {
        $this->authenticate($secret);

        $token = (string) $request->input('notification', '');

        if ($token === '' || strlen($token) > 128) {
            return response()->json(['status' => 'ignored'], 422);
        }

        try {
            $result = $this->webhooks->handleChargesNotification($token);
        } catch (EfiException $exception) {
            Log::warning('Notificação da Efí não lida; será reenviada.', ['error' => $exception->getMessage()]);

            return response()->json(['status' => 'retry'], 503);
        }

        return response()->json(['status' => 'ok', ...$result]);
    }

    private function authenticate(string $secret): void
    {
        $configured = (string) BillingSetting::current()->webhook_secret;

        abort_unless($configured !== '' && hash_equals($configured, $secret), 401);
    }
}
