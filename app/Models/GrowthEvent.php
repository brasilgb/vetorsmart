<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\GrowthEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property GrowthEventType $event_type
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property Carbon $occurred_at
 */
#[Fillable(['tenant_id', 'event_type', 'subject_type', 'subject_id', 'occurred_at'])]
class GrowthEvent extends Model
{
    use BelongsToTenant;

    /**
     * Events are append-only: they carry their own timestamp and have no update column.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_type' => GrowthEventType::class,
            'occurred_at' => 'datetime',
        ];
    }
}
