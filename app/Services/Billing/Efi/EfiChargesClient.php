<?php

namespace App\Services\Billing\Efi;

use App\Models\BillingSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Efí Cobranças API: billet with Pix (Bolix) in one step, query, cancel and the token notices. Amounts in cents.
 */
class EfiChargesClient
{
    public const string PRODUCTION_URL = 'https://cobrancas.api.efipay.com.br';

    public const string HOMOLOGATION_URL = 'https://cobrancas-h.api.efipay.com.br';

    public function __construct(private ?BillingSetting $settings = null) {}

    public function settings(): BillingSetting
    {
        return $this->settings ??= BillingSetting::current();
    }

    public function baseUrl(): string
    {
        return $this->settings()->isProduction() ? self::PRODUCTION_URL : self::HOMOLOGATION_URL;
    }

    /**
     * @param  list<array{name: string, value: int, amount: int}>  $items
     * @param  array<string, mixed>  $customer
     * @return array<int|string, mixed>
     */
    public function createBillet(array $items, array $customer, string $expireAt, string $customId, string $notificationUrl): array
    {
        return $this->data('emitir boleto', fn (PendingRequest $http) => $http->post('/v1/charge/one-step', [
            'items' => $items,
            'metadata' => ['custom_id' => $customId, 'notification_url' => $notificationUrl],
            'payment' => ['banking_billet' => ['customer' => $customer, 'expire_at' => $expireAt]],
        ]));
    }

    /**
     * @return array<int|string, mixed>
     */
    public function getCharge(int|string $chargeId): array
    {
        return $this->data('consultar cobrança', fn (PendingRequest $http) => $http->get("/v1/charge/{$chargeId}"));
    }

    /**
     * Events of a notice. The callback carries only the token; the content comes from this query.
     *
     * @return list<array<string, mixed>>
     */
    public function getNotification(string $token): array
    {
        return array_values((array) $this->data('consultar notificação', fn (PendingRequest $http) => $http->get('/v1/notification/'.rawurlencode($token))));
    }

    public function testConnection(): void
    {
        Cache::forget($this->tokenCacheKey());
        $this->token();
    }

    private function token(): string
    {
        $settings = $this->settings();

        if (! $settings->chargesConfigured()) {
            throw EfiException::notConfigured('Cobranças');
        }

        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = $this->client()
                ->withBasicAuth((string) $settings->efi_charges_client_id, (string) $settings->efi_charges_client_secret)
                ->post('/v1/authorize', ['grant_type' => 'client_credentials']);
        } catch (ConnectionException $exception) {
            throw EfiException::connection('autenticar Cobranças', $exception);
        }

        if (! $response->successful() || ! filled($response->json('access_token'))) {
            throw EfiException::fromResponse('autenticar Cobranças', $response);
        }

        $token = (string) $response->json('access_token');
        Cache::put($this->tokenCacheKey(), $token, max(60, (int) $response->json('expires_in', 600) - 60));

        return $token;
    }

    /**
     * @param  callable(PendingRequest): Response  $send
     * @return array<int|string, mixed>
     */
    private function data(string $operation, callable $send): array
    {
        $token = $this->token();

        try {
            $response = $send($this->client()->withToken($token));
        } catch (ConnectionException $exception) {
            throw EfiException::connection($operation, $exception);
        }

        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
        }

        if (! $response->successful()) {
            throw EfiException::fromResponse($operation, $response);
        }

        return (array) ($response->json('data') ?? []);
    }

    private function client(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())->acceptJson()->asJson()->timeout(20)->connectTimeout(10);
    }

    private function tokenCacheKey(): string
    {
        return 'billing:efi:charges:token:'.$this->settings()->efi_environment.':'.hash('sha256', (string) $this->settings()->efi_charges_client_id);
    }
}
