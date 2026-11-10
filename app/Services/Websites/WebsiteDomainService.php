<?php

namespace App\Services\Websites;

use App\Enums\DomainKind;
use App\Enums\DomainStatus;
use App\Enums\GrowthEventType;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\GrowthEventRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Manages the hostnames a company uses for its public site. Platform subdomains are
 * created verified; custom domains stay pending until the DNS record is seen.
 */
class WebsiteDomainService
{
    public function __construct(
        private HostnameNormalizer $normalizer,
        private DomainVerifier $verifier,
        private TrustedHosts $trusted,
        private AuditLogger $auditLogger,
        private GrowthEventRecorder $growth,
    ) {}

    /**
     * Create the platform subdomain for a company (e.g. clima-total.<base>).
     */
    public function createSubdomain(Tenant $tenant, User $actor): TenantDomain
    {
        $this->ensureLimit($tenant);

        return DB::transaction(function () use ($tenant, $actor): TenantDomain {
            $base = (string) config('platform.tenant_base_domain');
            $label = Str::limit(Str::slug($tenant->name) ?: 'empresa', 40, '');

            $candidate = $label;
            $suffix = 2;

            while (! $this->isAvailable($candidate.'.'.$base) || in_array($candidate, (array) config('platform.reserved_subdomains'), true)) {
                $candidate = $label.'-'.$suffix;
                $suffix++;
            }

            $domain = TenantDomain::create([
                'tenant_id' => $tenant->getKey(),
                'hostname' => $candidate.'.'.$base,
                'kind' => DomainKind::Subdomain,
                'status' => DomainStatus::Verified,
                'is_primary' => ! TenantDomain::query()->forTenant($tenant)->where('is_primary', true)->exists(),
                'verified_at' => now(),
            ]);

            $this->auditLogger->record('website.domain_added', $tenant, $domain, ['kind' => 'subdomain'], $actor);

            return $domain;
        });
    }

    /**
     * Register a custom domain as pending and return it with the TXT value to publish.
     */
    public function addCustomDomain(Tenant $tenant, string $rawHostname, User $actor): TenantDomain
    {
        $hostname = $this->normalizer->normalize($rawHostname);

        if ($hostname === null) {
            throw ValidationException::withMessages(['hostname' => 'Informe um domínio válido, como www.suaempresa.com.br.']);
        }

        if ($this->isPlatformName($hostname) || ! $this->isAvailable($hostname)) {
            throw ValidationException::withMessages(['hostname' => 'Este domínio não está disponível.']);
        }

        $this->ensureLimit($tenant);

        return DB::transaction(function () use ($tenant, $hostname, $actor): TenantDomain {
            $domain = TenantDomain::create([
                'tenant_id' => $tenant->getKey(),
                'hostname' => $hostname,
                'kind' => DomainKind::Custom,
                'status' => DomainStatus::Pending,
                'verification_token' => Str::random(32),
                'is_primary' => false,
            ]);

            $this->auditLogger->record('website.domain_added', $tenant, $domain, ['kind' => 'custom'], $actor);

            return $domain;
        });
    }

    /**
     * Check the DNS record. Returns true when the domain became verified now.
     */
    public function verify(TenantDomain $domain, User $actor): bool
    {
        if ($domain->status === DomainStatus::Verified) {
            return true;
        }

        $expected = $this->expectedRecord($domain);

        if (! $this->verifier->hasVerificationRecord($domain->hostname, $expected)) {
            return false;
        }

        $domain->forceFill(['status' => DomainStatus::Verified, 'verified_at' => now()])->save();
        $this->trusted->forgetCache();

        $tenant = $domain->tenant;
        $this->growth->record($tenant, GrowthEventType::DomainVerified, $domain);
        $this->auditLogger->record('website.domain_verified', $tenant, $domain, [], $actor);

        return true;
    }

    /**
     * Make a verified domain the one visitors are redirected to. Only one per company.
     */
    public function makePrimary(TenantDomain $domain, User $actor): void
    {
        if (! $domain->isServing()) {
            throw ValidationException::withMessages(['domain' => 'Somente um domínio verificado pode ser o principal.']);
        }

        DB::transaction(function () use ($domain, $actor): void {
            TenantDomain::query()->forTenant($domain->tenant_id)->where('is_primary', true)->update(['is_primary' => false]);
            $domain->forceFill(['is_primary' => true])->save();

            $this->auditLogger->record('website.domain_primary', $domain->tenant, $domain, [], $actor);
        });
    }

    public function remove(TenantDomain $domain, User $actor): void
    {
        $tenant = $domain->tenant;
        $hostname = $domain->hostname;

        $domain->delete();
        $this->trusted->forgetCache();

        $this->auditLogger->record('website.domain_removed', $tenant, null, ['hostname_kind' => str_contains($hostname, (string) config('platform.tenant_base_domain')) ? 'subdomain' : 'custom'], $actor);
    }

    public function expectedRecord(TenantDomain $domain): string
    {
        return 'vetorsmart-verify='.$domain->verification_token;
    }

    private function ensureLimit(Tenant $tenant): void
    {
        $count = TenantDomain::query()->forTenant($tenant)->count();

        if ($count >= (int) config('platform.max_domains_per_tenant')) {
            throw ValidationException::withMessages(['hostname' => 'Limite de domínios atingido para esta empresa.']);
        }
    }

    private function isAvailable(string $hostname): bool
    {
        return ! TenantDomain::query()->where('hostname', $hostname)->exists();
    }

    /**
     * Names inside the platform's own namespace cannot be claimed by a company.
     */
    private function isPlatformName(string $hostname): bool
    {
        $central = (string) config('platform.central_host');
        $base = (string) config('platform.tenant_base_domain');

        if ($hostname === $central || $hostname === $base || str_ends_with($hostname, '.'.$base)) {
            return true;
        }

        return false;
    }
}
