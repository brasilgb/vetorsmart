<?php

namespace App\Services\Billing;

use App\Models\BillingCharge;
use App\Models\BillingWebhookEvent;
use App\Services\Billing\Efi\EfiChargesClient;
use App\Services\Billing\Efi\EfiException;
use Illuminate\Support\Facades\DB;

/**
 * Gateway notices. Each event is recorded once by its key (a duplicate is answered and ignored), and the payment is
 * always confirmed by querying Efí: the content of a notice is never trusted on its own, so a forged or out-of-order
 * notice cannot settle or revert a charge. A failure is recorded and the daily routine reconciles the open charges.
 */
class BillingWebhookService
{
    public function __construct(
        private ChargeService $charges,
        private EfiChargesClient $chargesClient,
    ) {}

    /**
     * Pix notice: a `pix` list with txid and endToEndId of each payment received.
     *
     * @param  array<int, mixed>  $items
     * @return array{processed: int, duplicated: int, ignored: int, failed: int}
     */
    public function handlePix(array $items): array
    {
        $result = ['processed' => 0, 'duplicated' => 0, 'ignored' => 0, 'failed' => 0];

        foreach ($items as $item) {
            $txid = is_array($item) ? ($item['txid'] ?? null) : null;
            $endToEnd = is_array($item) ? ($item['endToEndId'] ?? null) : null;

            if (! is_string($txid) || $txid === '' || ! is_string($endToEnd) || $endToEnd === '') {
                $result['ignored']++;

                continue;
            }

            $outcome = $this->process('efi_pix', 'pix:'.$endToEnd, $txid, ['txid' => $txid, 'valor' => $item['valor'] ?? null, 'horario' => $item['horario'] ?? null]);
            $result[$outcome]++;
        }

        return $result;
    }

    /**
     * Cobranças notice: only a token. The events come from the API; each event id is processed once.
     *
     * @return array{processed: int, duplicated: int, ignored: int, failed: int}
     *
     * @throws EfiException when the events cannot be read: Efí keeps notifying until they are.
     */
    public function handleChargesNotification(string $token): array
    {
        $result = ['processed' => 0, 'duplicated' => 0, 'ignored' => 0, 'failed' => 0];

        foreach ($this->chargesClient->getNotification($token) as $event) {
            $chargeId = $event['identifiers']['charge_id'] ?? null;

            if ($chargeId === null) {
                $result['ignored']++;

                continue;
            }

            $outcome = $this->process('efi_charges', 'charges:'.hash('sha256', $token).':'.($event['id'] ?? '0'), (string) $chargeId, [
                'charge_id' => (string) $chargeId,
                'status' => $event['status']['current'] ?? null,
                'previous' => $event['status']['previous'] ?? null,
            ]);
            $result[$outcome]++;
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @return 'processed'|'duplicated'|'ignored'|'failed'
     */
    private function process(string $provider, string $key, string $reference, array $summary): string
    {
        $claimed = DB::table('billing_webhook_events')->insertOrIgnore([
            'provider' => $provider,
            'event_key' => $key,
            'reference' => mb_substr($reference, 0, 64),
            'status' => 'received',
            'summary' => json_encode($summary),
            'created_at' => now(),
        ]);

        if ($claimed === 0) {
            return 'duplicated';
        }

        $event = BillingWebhookEvent::query()->where('event_key', $key)->firstOrFail();
        $charge = BillingCharge::query()->where('gateway_id', $reference)->first();

        if ($charge === null) {
            $event->forceFill(['status' => 'ignored', 'error' => 'Cobrança desconhecida.', 'processed_at' => now()])->save();

            return 'ignored';
        }

        try {
            $this->charges->reconcile($charge);
        } catch (EfiException $exception) {
            $event->forceFill(['status' => 'failed', 'error' => mb_substr($exception->getMessage(), 0, 500), 'processed_at' => now()])->save();

            return 'failed';
        }

        $event->forceFill(['status' => 'processed', 'processed_at' => now()])->save();

        return 'processed';
    }
}
