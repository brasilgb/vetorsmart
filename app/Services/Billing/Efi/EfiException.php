<?php

namespace App\Services\Billing\Efi;

use Illuminate\Http\Client\Response;
use RuntimeException;
use Throwable;

/**
 * A failure at Efí with a message safe for screens and logs: only the HTTP status and the description returned by
 * the API, never credentials, tokens or certificates. `temporary` marks failures worth retrying (connection, 429, 5xx).
 */
class EfiException extends RuntimeException
{
    public function __construct(string $message, public readonly ?int $status = null, public readonly bool $temporary = false)
    {
        parent::__construct($message);
    }

    public static function fromResponse(string $operation, Response $response): self
    {
        $body = $response->json();
        $detail = is_array($body) ? ($body['error_description'] ?? $body['mensagem'] ?? $body['message'] ?? $body['nome'] ?? $body['error'] ?? null) : null;

        if (is_array($detail)) {
            $detail = $detail['message'] ?? json_encode($detail, JSON_UNESCAPED_UNICODE);
        }

        $status = $response->status();

        return new self("Efí ({$operation}): HTTP {$status}".($detail ? ' - '.mb_substr((string) $detail, 0, 200) : ''), $status, $status === 429 || $status >= 500);
    }

    public static function connection(string $operation, Throwable $exception): self
    {
        return new self("Efí ({$operation}): falha de conexão (".$exception::class.').', null, true);
    }

    public static function notConfigured(string $api): self
    {
        return new self("Efí ({$api}): credenciais não configuradas.", null, false);
    }
}
