<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Database\Factories\ProfessionalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * A person who performs services. Does not need a login.
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property bool $active
 * @property string|null $notes
 */
#[Fillable(['tenant_id', 'name', 'email', 'phone', 'active', 'notes'])]
class Professional extends Model
{
    /** @use HasFactory<ProfessionalFactory> */
    use BelongsToTenant, HasFactory;

    /**
     * Inactive professionals keep their history but cannot receive new appointments.
     */
    public function isAvailableForScheduling(): bool
    {
        return $this->active;
    }

    /**
     * @return HasMany<ProfessionalAvailability, $this>
     */
    public function availabilities(): HasMany
    {
        return $this->hasMany(ProfessionalAvailability::class);
    }

    /**
     * @return BelongsToMany<Service, $this, Pivot, 'pivot'>
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'professional_services')
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
            'active' => 'boolean',
        ];
    }
}
