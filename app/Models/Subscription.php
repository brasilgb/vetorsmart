<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\PaymentMethod;
use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The subscription of a company. Changed only through SubscriptionService, which validates the transition and
 * writes the history.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $plan_id
 * @property SubscriptionStatus $status
 * @property PaymentMethod|null $payment_method
 * @property int|null $price_cents
 * @property int $discount_percent
 * @property Carbon|null $discount_until
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $current_period_start
 * @property Carbon|null $current_period_end
 * @property Carbon|null $grace_ends_at
 * @property bool $cancel_at_period_end
 * @property Carbon|null $cancelled_at
 * @property Plan $plan
 * @property Tenant $tenant
 */
class Subscription extends Model
{
    use BelongsToTenant;

    /**
     * Nothing is mass assignable: every change goes through SubscriptionService.
     *
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return HasMany<BillingCharge, $this>
     */
    public function charges(): HasMany
    {
        return $this->hasMany(BillingCharge::class);
    }

    /**
     * @return HasMany<SubscriptionEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SubscriptionEvent::class);
    }

    /**
     * Amount of one period: the contracted price (or the plan's current price), less the discount while it lasts.
     */
    public function periodAmountCents(): ?int
    {
        $price = $this->price_cents ?? $this->plan->currentPriceCents();

        if ($price === null || $price <= 0) {
            return null;
        }

        $discountActive = $this->discount_percent > 0 && ($this->discount_until === null || now()->startOfDay()->lte($this->discount_until));

        return $discountActive ? (int) round($price * (100 - $this->discount_percent) / 100) : $price;
    }

    public function trialDaysLeft(): ?int
    {
        if ($this->status !== SubscriptionStatus::Trialing || $this->trial_ends_at === null) {
            return null;
        }

        return max(0, (int) ceil(now()->diffInSeconds($this->trial_ends_at, false) / 86400));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'payment_method' => PaymentMethod::class,
            'discount_until' => 'date',
            'trial_ends_at' => 'datetime',
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'grace_ends_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
            'cancelled_at' => 'datetime',
        ];
    }
}
