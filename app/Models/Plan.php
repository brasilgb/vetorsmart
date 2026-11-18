<?php

namespace App\Models;

use App\Enums\BillingInterval;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The commercial plan. Amounts are in cents. Each billing cycle has its own total price (price_cents is the monthly
 * one). A cycle without price cannot be contracted. While prices_approved_at is empty the prices are provisional and
 * no charge is issued in the production environment.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $price_cents
 * @property int|null $semiannual_price_cents
 * @property int|null $annual_price_cents
 * @property int $trial_days
 * @property int|null $included_users
 * @property int|null $extra_user_price_cents
 * @property int|null $ai_monthly_credits
 * @property int $promotional_discount_percent
 * @property Carbon|null $promotional_until
 * @property Carbon|null $prices_approved_at
 * @property bool $active
 */
#[Fillable(['name', 'description', 'price_cents', 'semiannual_price_cents', 'annual_price_cents', 'trial_days', 'included_users', 'extra_user_price_cents', 'ai_monthly_credits', 'promotional_discount_percent', 'promotional_until', 'active'])]
class Plan extends Model
{
    public const string MAIN = 'principal';

    public static function main(): self
    {
        return self::query()->where('code', self::MAIN)->firstOrFail();
    }

    public function isPriced(): bool
    {
        return $this->priceFor(BillingInterval::Monthly) !== null;
    }

    /**
     * Total list price of one period of the cycle, or null when the cycle has no price.
     */
    public function priceFor(BillingInterval $interval): ?int
    {
        $price = match ($interval) {
            BillingInterval::Monthly => $this->price_cents,
            BillingInterval::Semiannual => $this->semiannual_price_cents,
            BillingInterval::Annual => $this->annual_price_cents,
        };

        return $price !== null && $price > 0 ? $price : null;
    }

    /**
     * Discount of the promotion for new contracts today (0 when there is none).
     */
    public function activePromotionPercent(): int
    {
        if ($this->promotional_discount_percent <= 0 || ($this->promotional_until !== null && now()->startOfDay()->gt($this->promotional_until))) {
            return 0;
        }

        return $this->promotional_discount_percent;
    }

    public function pricesApproved(): bool
    {
        return $this->prices_approved_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'promotional_until' => 'date',
            'prices_approved_at' => 'datetime',
        ];
    }
}
