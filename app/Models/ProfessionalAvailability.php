<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\ProfessionalAvailabilityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A working window of a professional on one weekday (0 = Sunday ... 6 = Saturday).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $professional_id
 * @property int $weekday
 * @property string $start_time
 * @property string $end_time
 */
#[Fillable(['tenant_id', 'professional_id', 'weekday', 'start_time', 'end_time'])]
class ProfessionalAvailability extends Model
{
    /** @use HasFactory<ProfessionalAvailabilityFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * @return BelongsTo<Professional, $this>
     */
    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }
}
