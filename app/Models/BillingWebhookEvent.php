<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $event_key
 * @property string|null $reference
 * @property string $status
 * @property array<string, mixed>|null $summary
 * @property string|null $error
 * @property Carbon|null $created_at
 * @property Carbon|null $processed_at
 */
class BillingWebhookEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $guarded = ['*'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'summary' => 'array',
            'processed_at' => 'datetime',
        ];
    }
}
