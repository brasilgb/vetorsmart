<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\BillingInterval;
use App\Enums\MembershipStatus;
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
 * @property BillingInterval $billing_interval
 * @property BillingInterval|null $scheduled_interval
 * @property int|null $price_cents
 * @property int|null $extra_user_price_cents
 * @property int $extra_seats
 * @property int|null $scheduled_extra_seats
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
     * Amount of the next period of a cycle: the contracted price (frozen at contracting; while empty, the plan's current
     * price for the cycle), less the discount while it lasts, plus the additional seats contracted for that period (a
     * scheduled reduction counts), each at the contracted monthly extra-user price times the months of the cycle.
     * Registered users do not change the amount. Null when the cycle has no price.
     *
     * @return array{interval: BillingInterval, months: int, base_cents: int, discount_cents: int, active_users: int, included_users: int|null, extra_users: int, extra_user_price_cents: int, extra_users_cents: int, total_cents: int}|null
     */
    public function quote(?BillingInterval $interval = null): ?array
    {
        $interval ??= $this->billing_interval;
        $base = $interval === $this->billing_interval && $this->price_cents !== null ? $this->price_cents : $this->plan->priceFor($interval);

        if ($base === null || $base <= 0) {
            return null;
        }

        $discountActive = $this->discount_percent > 0 && ($this->discount_until === null || now()->startOfDay()->lte($this->discount_until));
        $discount = $discountActive ? (int) round($base * $this->discount_percent / 100) : 0;

        $activeUsers = TenantUser::query()->where('tenant_id', $this->tenant_id)->where('status', MembershipStatus::Active->value)->count();
        $included = $this->plan->included_users;
        $extraUsers = $included === null ? 0 : ($this->scheduled_extra_seats ?? $this->extra_seats);
        $extraPrice = $this->extra_user_price_cents ?? $this->plan->extra_user_price_cents ?? 0;
        $extraAmount = $extraUsers * $extraPrice * $interval->months();

        return [
            'interval' => $interval,
            'months' => $interval->months(),
            'base_cents' => $base,
            'discount_cents' => $discount,
            'active_users' => $activeUsers,
            'included_users' => $included,
            'extra_users' => $extraUsers,
            'extra_user_price_cents' => $extraPrice,
            'extra_users_cents' => $extraAmount,
            'total_cents' => $base - $discount + $extraAmount,
        ];
    }

    /**
     * Total of the next period of the current cycle, or null when the cycle has no price.
     */
    public function periodAmountCents(): ?int
    {
        return $this->quote()['total_cents'] ?? null;
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
            'billing_interval' => BillingInterval::class,
            'scheduled_interval' => BillingInterval::class,
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
