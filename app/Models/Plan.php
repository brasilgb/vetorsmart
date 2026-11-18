<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A commercial plan. Amounts are in cents. A plan without price allows the trial but not contracting.
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $price_cents
 * @property int $interval_months
 * @property int $trial_days
 * @property int|null $included_users
 * @property int|null $extra_user_price_cents
 * @property int|null $ai_messages_per_month
 * @property int|null $promotional_price_cents
 * @property Carbon|null $promotional_until
 * @property bool $active
 */
#[Fillable(['name', 'description', 'price_cents', 'interval_months', 'trial_days', 'included_users', 'extra_user_price_cents', 'ai_messages_per_month', 'promotional_price_cents', 'promotional_until', 'active'])]
class Plan extends Model
{
    public const string MAIN = 'principal';

    public static function main(): self
    {
        return self::query()->where('code', self::MAIN)->firstOrFail();
    }

    public function isPriced(): bool
    {
        return $this->price_cents !== null && $this->price_cents > 0;
    }

    /**
     * Price for a new contract today: the promotional price while it lasts, otherwise the list price.
     */
    public function currentPriceCents(): ?int
    {
        if ($this->promotional_price_cents !== null && $this->promotional_until !== null && now()->startOfDay()->lte($this->promotional_until)) {
            return $this->promotional_price_cents;
        }

        return $this->price_cents;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'active' => 'boolean',
            'promotional_until' => 'date',
        ];
    }
}
