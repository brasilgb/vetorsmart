<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\AiCreditPurchaseStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A purchase of additional AI credits: a charge separate from the subscription. Changed only through
 * AiCreditPurchaseService.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int|null $package_id
 * @property string $package_name
 * @property int $credits
 * @property int $price_cents
 * @property AiCreditPurchaseStatus $status
 * @property string $idempotency_key
 * @property Carbon|null $paid_at
 * @property string|null $paid_via
 * @property int $refunded_credits
 * @property int $refunded_cents
 * @property string|null $note
 * @property int|null $user_id
 * @property Carbon|null $created_at
 * @property Tenant $tenant
 */
class AiCreditPurchase extends Model
{
    use BelongsToTenant;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AiCreditPurchaseStatus::class,
            'paid_at' => 'datetime',
        ];
    }
}
