<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\BillingSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int $subscription_id
 * @property string $type
 * @property string|null $from_status
 * @property string|null $to_status
 * @property BillingSource $source
 * @property string|null $note
 * @property int|null $user_id
 * @property Carbon|null $created_at
 */
class SubscriptionEvent extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

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
        return ['source' => BillingSource::class];
    }
}
