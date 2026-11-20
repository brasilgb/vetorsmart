<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Search entry of the Regional Portal for one company, built from its published site version only (PortalDirectory).
 * It never decides on its own whether a company appears: the search checks eligibility again on every query.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $website_version_id
 * @property int|null $segment_id
 * @property string $business_name
 * @property string $city
 * @property string $state
 * @property int|null $municipality_id IBGE code of the municipality where the company is based
 * @property string|null $region
 * @property string|null $summary
 * @property list<array{id: int|null, name: string}> $services
 * @property array{file: string, width: int, height: int}|null $logo
 * @property int $completeness
 */
#[Fillable(['tenant_id', 'website_version_id', 'segment_id', 'business_name', 'name_search', 'segment_search', 'city', 'city_search', 'state', 'municipality_id', 'region', 'region_search', 'summary', 'services', 'services_search', 'logo', 'completeness'])]
class PortalListing extends Model
{
    use BelongsToTenant;

    /**
     * @return BelongsTo<Segment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'services' => 'array',
            'logo' => 'array',
            'completeness' => 'integer',
        ];
    }
}
