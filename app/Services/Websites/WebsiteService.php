<?php

namespace App\Services\Websites;

use App\Enums\GrowthEventType;
use App\Enums\WebsiteStatus;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\TenantWebsite;
use App\Models\User;
use App\Models\WebsiteVersion;
use App\Services\AuditLogger;
use App\Services\GrowthEventRecorder;
use Illuminate\Support\Facades\DB;

/**
 * Draft editing, atomic publication and unpublication of a company's site.
 */
class WebsiteService
{
    public const string TEMPLATE_LABEL_FALLBACK = 'Modelo base';

    public function __construct(
        private WebsiteSnapshotBuilder $builder,
        private AuditLogger $auditLogger,
        private GrowthEventRecorder $growth,
    ) {}

    /**
     * The company's site, created on first access with empty draft content.
     */
    public function ensure(Tenant $tenant): TenantWebsite
    {
        return TenantWebsite::query()->forTenant($tenant)->firstOrCreate(
            ['tenant_id' => $tenant->getKey()],
            [
                'name' => $tenant->name,
                'template' => 'base',
                'status' => WebsiteStatus::Draft,
                'draft_content' => WebsiteDraft::defaults(),
            ],
        );
    }

    /**
     * Save changes to the draft only. Visitors do not see them until publication.
     *
     * @param  array<string, mixed>  $changes  validated fields
     */
    public function saveDraft(Tenant $tenant, array $changes, User $actor): TenantWebsite
    {
        $website = $this->ensure($tenant);

        $draft = array_merge($website->draft_content ?? WebsiteDraft::defaults(), $changes);

        $website->draft_content = $draft;
        $firstConfiguration = $website->configured_at === null;

        if ($firstConfiguration) {
            $website->configured_at = now();
        }

        $website->save();

        if ($firstConfiguration) {
            $this->growth->record($tenant, GrowthEventType::WebsiteConfigured, $website);
        }

        $this->auditLogger->record('website.draft_saved', $tenant, $website, ['fields' => array_keys($changes)], $actor);

        return $website;
    }

    /**
     * Freeze the current draft into a new immutable version and point the site at it.
     * Everything happens in one transaction: visitors see either the old version or the new one.
     */
    public function publish(Tenant $tenant, User $actor): WebsiteVersion
    {
        $website = $this->ensure($tenant);

        return DB::transaction(function () use ($tenant, $website, $actor): WebsiteVersion {
            $tenant->loadMissing('segment');

            $content = $this->builder->build($tenant, $website->draft_content ?? [], $this->templateLabel($tenant));

            $nextVersion = (int) WebsiteVersion::query()->where('tenant_website_id', $website->getKey())->max('version') + 1;

            $version = WebsiteVersion::create([
                'tenant_id' => $tenant->getKey(),
                'tenant_website_id' => $website->getKey(),
                'version' => $nextVersion,
                'content' => $content,
                'published_by' => $actor->getKey(),
                'published_at' => now(),
            ]);

            $website->forceFill([
                'status' => WebsiteStatus::Published,
                'published_version_id' => $version->getKey(),
                'published_at' => now(),
                'unpublished_at' => null,
            ])->save();

            $this->growth->record($tenant, GrowthEventType::WebsitePublished, $website);
            $this->auditLogger->record('website.published', $tenant, $website, ['version' => $nextVersion], $actor);

            return $version;
        });
    }

    /**
     * Take the site offline without deleting its data or its published history.
     */
    public function unpublish(Tenant $tenant, User $actor): TenantWebsite
    {
        $website = $this->ensure($tenant);

        $website->forceFill([
            'status' => WebsiteStatus::Unpublished,
            'unpublished_at' => now(),
        ])->save();

        $this->auditLogger->record('website.unpublished', $tenant, $website, [], $actor);

        return $website;
    }

    /**
     * Preview of the current draft. Never stored and never visible to visitors.
     *
     * @return array<string, mixed>
     */
    public function previewContent(Tenant $tenant): array
    {
        $website = $this->ensure($tenant);
        $tenant->loadMissing('segment');

        return $this->builder->build($tenant, $website->draft_content ?? [], $this->templateLabel($tenant));
    }

    private function templateLabel(Tenant $tenant): string
    {
        $name = $tenant->segment_id === null ? null : Segment::query()->whereKey($tenant->segment_id)->value('name');

        return is_string($name) && $name !== '' ? $name : self::TEMPLATE_LABEL_FALLBACK;
    }
}
