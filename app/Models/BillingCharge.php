<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\ChargeStatus;
use App\Enums\PaymentMethod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The charge of one subscription period. Changed only through ChargeService.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $subscription_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property int $amount_cents
 * @property Carbon $due_date
 * @property ChargeStatus $status
 * @property PaymentMethod $method
 * @property string $gateway
 * @property string|null $gateway_id
 * @property string|null $pix_copy_paste
 * @property string|null $billet_url
 * @property string|null $billet_pdf
 * @property string|null $billet_barcode
 * @property int|null $paid_amount_cents
 * @property Carbon|null $paid_at
 * @property string|null $paid_via
 * @property int $issue_attempts
 * @property string|null $last_error
 * @property Subscription $subscription
 * @property Tenant $tenant
 */
class BillingCharge extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Pix transaction id: 26 to 35 alphanumeric characters, derived from the charge id, so a retried issue of the
     * same charge reuses the same txid and Efí refuses a second one.
     */
    public function pixTxid(): string
    {
        return 'VSMART'.str_pad((string) $this->getKey(), 26, '0', STR_PAD_LEFT);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChargeStatus::class,
            'method' => PaymentMethod::class,
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'due_date' => 'date',
            'paid_at' => 'datetime',
        ];
    }
}
