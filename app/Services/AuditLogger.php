<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Records administrative and sensitive operations.
 *
 * Metadata is filtered so credentials and secrets are never persisted, even
 * when a caller passes them by mistake.
 */
class AuditLogger
{
    /**
     * Key fragments whose values must never be stored.
     *
     * @var list<string>
     */
    private const array SENSITIVE_KEY_FRAGMENTS = ['password', 'token', 'secret', 'two_factor', 'remember', 'api_key', 'key'];

    /**
     * Record an audit entry.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(string $action, ?Tenant $tenant, ?Model $subject = null, array $metadata = [], ?User $actor = null): AuditLog
    {
        return AuditLog::create([
            'tenant_id' => $tenant?->getKey(),
            'user_id' => ($actor ?? Auth::user())?->getKey(),
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'metadata' => $this->sanitize($metadata),
        ]);
    }

    /**
     * Remove sensitive keys, recursively.
     *
     * @param  array<array-key, mixed>  $metadata
     * @return array<array-key, mixed>
     */
    private function sanitize(array $metadata): array
    {
        $clean = [];

        foreach ($metadata as $key => $value) {
            if (is_string($key) && $this->isSensitive($key)) {
                continue;
            }

            $clean[$key] = is_array($value) ? $this->sanitize($value) : $value;
        }

        return $clean;
    }

    /**
     * Determine whether a metadata key holds sensitive data.
     */
    private function isSensitive(string $key): bool
    {
        $normalized = strtolower($key);

        foreach (self::SENSITIVE_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalized, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
