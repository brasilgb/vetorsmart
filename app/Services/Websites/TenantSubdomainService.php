<?php

namespace App\Services\Websites;

use App\Enums\DomainKind;
use App\Enums\DomainStatus;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The company's subdomain on the platform ({name}.<tenant_base_domain>), its public identifier on the web.
 *
 * Every subdomain a company ever used is a row in tenant_domains, whose hostname is globally unique: the current one has
 * no retired_at, the earlier ones do and only redirect to it. Because the rows are never deleted, a name that belonged
 * to one company can never be taken by another, and the unique index settles simultaneous registrations.
 *
 * The name is independent of the company's display name and of its standard address: renaming the company does not
 * change it, only an explicit change by an administrator does.
 */
class TenantSubdomainService
{
    /**
     * Shortest and longest accepted name. The upper bound is the DNS label limit.
     */
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 63;

    private const string LABEL_PATTERN = '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/';

    public function __construct(private TrustedHosts $trusted, private AuditLogger $auditLogger) {}

    /**
     * The current subdomain of the company, or null when it has none yet.
     */
    public function current(Tenant $tenant): ?TenantDomain
    {
        return TenantDomain::query()
            ->forTenant($tenant)
            ->where('kind', DomainKind::Subdomain)
            ->where('status', DomainStatus::Verified)
            ->whereNull('retired_at')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->first();
    }

    /**
     * Full hostname of a name: climatotal → climatotal.vetorsmart.com.br.
     */
    public function hostnameFor(string $label): string
    {
        return $label.'.'.config('platform.tenant_base_domain');
    }

    /**
     * Name part of a platform hostname: climatotal.vetorsmart.com.br → climatotal.
     */
    public function labelOf(TenantDomain $domain): string
    {
        return Str::before($domain->hostname, '.'.config('platform.tenant_base_domain'));
    }

    /**
     * Give the company a subdomain derived from its slug when it has none. Idempotent. A name taken at the same moment
     * by another registration hits the unique index and the next candidate is tried.
     */
    public function assign(Tenant $tenant): TenantDomain
    {
        $existing = $this->current($tenant);

        if ($existing !== null) {
            return $existing;
        }

        foreach ($this->candidates($tenant) as $label) {
            if ($this->problemWith($label) !== null || $this->isTaken($label)) {
                continue;
            }

            try {
                $domain = DB::transaction(fn (): TenantDomain => TenantDomain::create([
                    'tenant_id' => $tenant->getKey(),
                    'hostname' => $this->hostnameFor($label),
                    'kind' => DomainKind::Subdomain,
                    'status' => DomainStatus::Verified,
                    'is_primary' => false,
                    'verified_at' => now(),
                ]));
            } catch (UniqueConstraintViolationException) {
                continue;
            }

            $this->auditLogger->record('website.subdomain_assigned', $tenant, $domain, ['hostname' => $domain->hostname]);

            return $domain;
        }

        throw new \RuntimeException('Não foi possível gerar um subdomínio livre para a empresa '.$tenant->getKey().'.');
    }

    /**
     * Change the company's subdomain. The earlier name stays with the company and redirects to the new one. A name the
     * company used before can be taken back; a name of another company never.
     */
    public function change(Tenant $tenant, string $rawLabel, User $actor): TenantDomain
    {
        $label = Str::lower(trim($rawLabel));
        $problem = $this->problemWith($label);

        if ($problem !== null) {
            throw ValidationException::withMessages(['subdomain' => $problem]);
        }

        return DB::transaction(function () use ($tenant, $label, $actor): TenantDomain {
            // Serialises changes of the same company, so two tabs cannot leave it with two current subdomains.
            Tenant::query()->whereKey($tenant->getKey())->lockForUpdate()->first();

            $current = $this->current($tenant);
            $hostname = $this->hostnameFor($label);

            if ($current !== null && $current->hostname === $hostname) {
                return $current;
            }

            $owned = TenantDomain::query()->where('hostname', $hostname)->first();

            if ($owned !== null && ($owned->tenant_id !== $tenant->getKey() || $owned->kind !== DomainKind::Subdomain)) {
                throw ValidationException::withMessages(['subdomain' => 'Este endereço não está disponível.']);
            }

            if ($this->changesUsed($tenant) >= (int) config('platform.max_subdomain_changes')) {
                throw ValidationException::withMessages(['subdomain' => 'Limite de alterações do endereço atingido. Fale com o suporte.']);
            }

            $current?->forceFill(['retired_at' => now()])->save();

            if ($owned !== null) {
                $owned->forceFill(['retired_at' => null, 'status' => DomainStatus::Verified])->save();
                $domain = $owned;
            } else {
                try {
                    $domain = DB::transaction(fn (): TenantDomain => TenantDomain::create([
                        'tenant_id' => $tenant->getKey(),
                        'hostname' => $hostname,
                        'kind' => DomainKind::Subdomain,
                        'status' => DomainStatus::Verified,
                        'is_primary' => false,
                        'verified_at' => now(),
                    ]));
                } catch (UniqueConstraintViolationException) {
                    throw ValidationException::withMessages(['subdomain' => 'Este endereço não está disponível.']);
                }
            }

            $this->trusted->forgetCache();
            $this->auditLogger->record('website.subdomain_changed', $tenant, $domain, [
                'from' => $current?->hostname,
                'to' => $domain->hostname,
            ], $actor);

            return $domain;
        });
    }

    /**
     * Why a name cannot be used, in words for the company, or null when it is valid. Availability is checked apart.
     */
    public function problemWith(string $label): ?string
    {
        $length = strlen($label);

        return match (true) {
            $length < self::MIN_LENGTH || $length > self::MAX_LENGTH => 'Use de '.self::MIN_LENGTH.' a '.self::MAX_LENGTH.' caracteres.',
            preg_match(self::LABEL_PATTERN, $label) !== 1 => 'Use apenas letras minúsculas sem acento, números e hífen, sem hífen no início ou no fim.',
            str_contains($label, '--') => 'Não use dois hífens seguidos.',
            in_array($label, (array) config('platform.reserved_subdomains'), true) => 'Este endereço é reservado pela plataforma.',
            default => null,
        };
    }

    /**
     * How many times the company already changed its subdomain (its retired names).
     */
    public function changesUsed(Tenant $tenant): int
    {
        return TenantDomain::query()
            ->forTenant($tenant)
            ->where('kind', DomainKind::Subdomain)
            ->whereNotNull('retired_at')
            ->count();
    }

    /**
     * Earlier names of the company, newest first.
     *
     * @return list<string>
     */
    public function retiredHostnames(Tenant $tenant): array
    {
        return array_values(TenantDomain::query()
            ->forTenant($tenant)
            ->where('kind', DomainKind::Subdomain)
            ->whereNotNull('retired_at')
            ->orderByDesc('retired_at')
            ->pluck('hostname')
            ->map(fn ($hostname): string => (string) $hostname)
            ->all());
    }

    private function isTaken(string $label): bool
    {
        return TenantDomain::query()->where('hostname', $this->hostnameFor($label))->exists();
    }

    /**
     * Names to try, in order: the company name without spaces or accents (Clima Total → climatotal), then numbered
     * variants (climatotal2), then random ones. The name is used rather than the slug, whose collision suffix is random.
     *
     * @return iterable<string>
     */
    private function candidates(Tenant $tenant): iterable
    {
        $base = str_replace('-', '', Str::slug((string) $tenant->name) ?: Str::slug((string) $tenant->slug));
        $base = $base === '' ? 'empresa' : $base;

        if (strlen($base) < self::MIN_LENGTH) {
            $base = 'empresa'.$base;
        }

        $base = substr($base, 0, self::MAX_LENGTH - 6);

        yield $base;

        for ($number = 2; $number <= 20; $number++) {
            yield $base.$number;
        }

        for ($attempt = 0; $attempt < 5; $attempt++) {
            yield $base.Str::lower(Str::random(5));
        }
    }
}
