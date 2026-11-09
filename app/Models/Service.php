<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\PriceType;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $tenant_id
 * @property int|null $service_category_id
 * @property string $name
 * @property string|null $description
 * @property int|null $duration_minutes
 * @property string|null $price
 * @property PriceType $price_type
 * @property bool $requires_evaluation
 * @property bool $active
 * @property int $sort_order
 */
#[Fillable(['tenant_id', 'service_category_id', 'name', 'description', 'duration_minutes', 'price', 'price_type', 'requires_evaluation', 'active', 'sort_order'])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Inactive services keep their history but cannot receive new appointments.
     */
    public function isAvailableForScheduling(): bool
    {
        return $this->active;
    }

    /**
     * @return BelongsTo<ServiceCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /**
     * @return BelongsToMany<Professional, $this>
     */
    public function professionals(): BelongsToMany
    {
        return $this->belongsToMany(Professional::class, 'professional_services')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'price_type' => PriceType::class,
            'requires_evaluation' => 'boolean',
            'active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
