<?php

namespace App\Services\Billing\Efi;

use App\Models\BillingSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Efí Pix API: OAuth2 client_credentials over mTLS (certificate in PEM, stored encrypted and written to a 0600 temp
 * file only during the call). Immediate charges (cob), query and QR code. Homologation by default.
 */
class EfiPixClient
{
    public const string PRODUCTION_URL = 'https://pix.api.efipay.com.br';

    public const string HOMOLOGATION_URL = 'https://pix-h.api.efipay.com.br';

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
     * Immediate charge with our own txid. PUT is keyed by the txid, so a repeated call cannot create a second charge.
     *
     * @return array<string, mixed>
     */
    public function createImmediateCharge(string $txid, string $amount, int $expiresInSeconds, string $description): array
    {
        return $this->request('criar cobrança Pix', fn (PendingRequest $http) => $http->put("/v2/cob/{$txid}", [
            'calendario' => ['expiracao' => $expiresInSeconds],
            'valor' => ['original' => $amount],
            'chave' => $this->settings()->efi_pix_key,
            'solicitacaoPagador' => Str::limit($description, 140, ''),
        ]));
    }

    /**
     * @return array<string, mixed>
     */
    public function getCharge(string $txid): array
    {
        return $this->request('consultar cobrança Pix', fn (PendingRequest $http) => $http->get("/v2/cob/{$txid}"));
    }

    public function testConnection(): void
    {
        Cache::forget($this->tokenCacheKey());
        $this->token();
    }

    private function token(): string
    {
        $settings = $this->settings();

        if (! $settings->pixConfigured()) {
            throw EfiException::notConfigured('Pix');
        }

        $cached = Cache::get($this->tokenCacheKey());

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        $response = $this->withCertificate(function (?string $certificate) use ($settings): Response {
            try {
                return $this->client($certificate)
                    ->withBasicAuth((string) $settings->efi_pix_client_id, (string) $settings->efi_pix_client_secret)
                    ->post('/oauth/token', ['grant_type' => 'client_credentials']);
            } catch (ConnectionException $exception) {
                throw EfiException::connection('autenticar Pix', $exception);
            }
        });

        if (! $response->successful() || ! filled($response->json('access_token'))) {
            throw EfiException::fromResponse('autenticar Pix', $response);
        }

        $token = (string) $response->json('access_token');
        Cache::put($this->tokenCacheKey(), $token, max(60, (int) $response->json('expires_in', 3600) - 120));

        return $token;
    }

    /**
     * @param  callable(PendingRequest): Response  $send
     * @return array<string, mixed>
     */
    private function request(string $operation, callable $send): array
    {
        $token = $this->token();

        $response = $this->withCertificate(function (?string $certificate) use ($send, $token, $operation): Response {
            try {
                return $send($this->client($certificate)->withToken($token));
            } catch (ConnectionException $exception) {
                throw EfiException::connection($operation, $exception);
            }
        });

        if ($response->status() === 401) {
            Cache::forget($this->tokenCacheKey());
        }

        if (! $response->successful()) {
            throw EfiException::fromResponse($operation, $response);
        }

        return (array) ($response->json() ?? []);
    }

    private function client(?string $certificate): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())->acceptJson()->asJson()->timeout(20)->connectTimeout(10)
            ->withOptions($certificate !== null ? ['cert' => $certificate] : []);
    }

    /**
     * @param  callable(?string): Response  $callback
     */
    private function withCertificate(callable $callback): Response
    {
        $pem = (string) $this->settings()->efi_pix_certificate;

        if ($pem === '') {
            return $callback(null);
        }

        $path = (string) tempnam(sys_get_temp_dir(), 'efi');

        try {
            chmod($path, 0600);
            file_put_contents($path, $pem);

            return $callback($path);
        } finally {
            @unlink($path);
        }
    }

    private function tokenCacheKey(): string
    {
        return 'billing:efi:pix:token:'.$this->settings()->efi_environment.':'.hash('sha256', (string) $this->settings()->efi_pix_client_id);
    }
}
