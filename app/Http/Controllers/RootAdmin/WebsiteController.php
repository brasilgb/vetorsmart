<?php

namespace App\Http\Controllers\RootAdmin;

use App\Enums\DomainKind;
use App\Enums\DomainStatus;
use App\Enums\WebsiteStatus;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantWebsite;
use App\Support\PlatformTime;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Sites e Domínios: the site of each company (draft, published, unpublished) and its addresses (subdomain and own
 * domains, with verification state and the primary one). Read-only: publishing and domains stay with each company,
 * and the verification token is never shown.
 */
class WebsiteController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $status = WebsiteStatus::tryFrom($request->string('status')->value());
        $term = $request->string('q')->trim()->value();

        $websites = TenantWebsite::query()
            ->with('tenant:id,name,is_homolog,status')
            ->when($status !== null, fn ($query) => $query->where('status', $status->value))
            ->when($term !== '', fn ($query) => $query->whereHas('tenant', fn ($inner) => $inner->where('name', 'like', '%'.addcslashes($term, '%_\\').'%')))
            ->orderByDesc('published_at')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $domains = TenantDomain::query()
            ->whereIn('tenant_id', $websites->getCollection()->pluck('tenant_id'))
            ->whereNull('retired_at')
            ->orderByDesc('is_primary')
            ->orderBy('hostname')
            ->get(['id', 'tenant_id', 'hostname', 'kind', 'status', 'is_primary', 'verified_at'])
            ->groupBy('tenant_id');

        $counts = TenantWebsite::query()->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('rootadmin/websites/index', [
            'filters' => ['status' => $status->value ?? '', 'q' => $term],
            'counts' => [
                'published' => (int) ($counts[WebsiteStatus::Published->value] ?? 0),
                'draft' => (int) ($counts[WebsiteStatus::Draft->value] ?? 0),
                'unpublished' => (int) ($counts[WebsiteStatus::Unpublished->value] ?? 0),
                'custom_domains_verified' => TenantDomain::query()->whereNull('retired_at')->where('kind', DomainKind::Custom->value)->where('status', DomainStatus::Verified->value)->count(),
                'custom_domains_pending' => TenantDomain::query()->whereNull('retired_at')->where('kind', DomainKind::Custom->value)->where('status', '!=', DomainStatus::Verified->value)->count(),
                'without_site' => Tenant::query()->whereNotIn('id', TenantWebsite::query()->select('tenant_id'))->count(),
            ],
            'websites' => $websites->through(fn (TenantWebsite $website): array => [
                'id' => $website->id,
                'tenant_id' => $website->tenant_id,
                'tenant' => $website->tenant->name,
                'is_homolog' => $website->tenant->isHomolog(),
                'tenant_suspended' => $website->tenant->status->value !== 'active',
                'name' => $website->name,
                'status' => $website->status->value,
                'published_at' => PlatformTime::dateTime($website->published_at),
                'domains' => ($domains->get($website->tenant_id) ?? collect())->map(fn (TenantDomain $domain): array => [
                    'id' => $domain->id,
                    'hostname' => $domain->hostname,
                    'kind' => $domain->kind->value,
                    'status' => $domain->status->value,
                    'is_primary' => $domain->is_primary,
                    'verified_at' => PlatformTime::date($domain->verified_at),
                ])->values()->all(),
            ]),
        ]);
    }
}
