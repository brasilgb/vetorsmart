<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Billing rules and Efí credentials (single row). Secrets are encrypted at rest and hidden from serialization.
 *
 * @property int $id
 * @property string $efi_environment
 * @property string|null $efi_pix_client_id
 * @property string|null $efi_pix_client_secret
 * @property string|null $efi_pix_certificate
 * @property string|null $efi_pix_key
 * @property string|null $efi_charges_client_id
 * @property string|null $efi_charges_client_secret
 * @property string|null $webhook_secret
 * @property int $grace_days
 * @property int $renewal_days_before
 * @property int $billet_due_days
 * @property int $ai_tokens_per_credit
 * @property int $ai_low_balance_percent
 * @property bool $ai_credit_purchases_enabled
 * @property Carbon|null $enforcement_starts_at
 * @property int|null $enforcement_scheduled_by
 * @property Carbon|null $connection_tested_at
 */
#[Hidden(['efi_pix_client_id', 'efi_pix_client_secret', 'efi_pix_certificate', 'efi_charges_client_id', 'efi_charges_client_secret', 'webhook_secret'])]
class BillingSetting extends Model
{
    public static function current(): self
    {
        $settings = self::query()->orderBy('id')->first();

        if ($settings === null) {
            $settings = new self;
            $settings->forceFill(['efi_environment' => 'homologation'])->save();
        }

        return $settings;
    }

    public function isProduction(): bool
    {
        return $this->efi_environment === 'production';
    }

    public function pixConfigured(): bool
    {
        return filled($this->efi_pix_client_id) && filled($this->efi_pix_client_secret) && filled($this->efi_pix_certificate) && filled($this->efi_pix_key);
    }

    public function chargesConfigured(): bool
    {
        return filled($this->efi_charges_client_id) && filled($this->efi_charges_client_secret);
    }

    /**
     * The secret that authenticates the gateway notices, created on first use.
     */
    public function webhookSecret(): string
    {
        if (blank($this->webhook_secret)) {
            $this->forceFill(['webhook_secret' => bin2hex(random_bytes(24))])->save();
        }

        return (string) $this->webhook_secret;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'efi_pix_client_id' => 'encrypted',
            'efi_pix_client_secret' => 'encrypted',
            'efi_pix_certificate' => 'encrypted',
            'efi_charges_client_id' => 'encrypted',
            'efi_charges_client_secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'connection_tested_at' => 'datetime',
            'ai_credit_purchases_enabled' => 'boolean',
            'enforcement_starts_at' => 'datetime',
        ];
    }
}
