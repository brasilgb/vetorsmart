<?php

namespace App\Models;

use App\Concerns\BelongsToTenant;
use App\Enums\WebsiteStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tenant_id
 * @property Carbon|null $published_at
 * @property Carbon|null $updated_at
 * @property string $name
 * @property string $template
 * @property WebsiteStatus $status
 * @property array<string, mixed> $draft_content
 * @property int|null $published_version_id
 */
#[Fillable(['tenant_id', 'name', 'template', 'status', 'draft_content', 'published_version_id', 'configured_at', 'published_at', 'unpublished_at'])]
class TenantWebsite extends Model
{
    use BelongsToTenant;

    /**
     * @return HasMany<WebsiteVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(WebsiteVersion::class);
    }

    /**
     * Only a published site with a snapshot is visible to visitors.
     */
    public function isLive(): bool
    {
        return $this->status === WebsiteStatus::Published && $this->published_version_id !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => WebsiteStatus::class,
            'draft_content' => 'array',
            'configured_at' => 'datetime',
            'published_at' => 'datetime',
            'unpublished_at' => 'datetime',
        ];
    }
}
