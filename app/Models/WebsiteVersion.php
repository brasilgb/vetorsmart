<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable snapshot of a publication. Never updated after creation.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $tenant_website_id
 * @property int $version
 * @property array<string, mixed> $content
 */
#[Fillable(['tenant_id', 'tenant_website_id', 'version', 'content', 'published_by', 'published_at'])]
class WebsiteVersion extends Model
{
    use BelongsToTenant;

    /** @var bool */
    public $timestamps = false;

    /**
     * @return BelongsTo<TenantWebsite, $this>
     */
    public function website(): BelongsTo
    {
        return $this->belongsTo(TenantWebsite::class, 'tenant_website_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
