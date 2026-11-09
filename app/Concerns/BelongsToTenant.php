<?php

namespace App\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    /**
     * Get the tenant that owns this record.
     *
     * @return BelongsTo<Tenant, $this>
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Limit the query to records belonging to the given tenant.
     *
     * Scopes are explicit on purpose: no global scope is applied, so
     * platform-level operations are not silently filtered.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForTenant(Builder $query, Tenant|int $tenant): Builder
    {
        return $query->where($this->qualifyColumn('tenant_id'), $tenant instanceof Tenant ? $tenant->getKey() : $tenant);
    }
}
