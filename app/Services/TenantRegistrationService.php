<?php

namespace App\Services;

use App\Enums\BillingSource;
use App\Enums\MembershipStatus;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Services\Websites\TenantSubdomainService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a company and its owner in one transaction. Either every step is
 * persisted or none is.
 */
final class TenantRegistrationService
{
    public function __construct(
        private AuditLogger $auditLogger,
        private TenantSubdomainService $subdomains,
        private SubscriptionService $subscriptions,
    ) {}

    /**
     * @param  array{company_name: string, segment_id: int, name: string, email: string, password: string}  $data
     */
    public function register(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'status' => UserStatus::Active,
            ]);

            $tenant = $this->createTenant($data);

            TenantUser::create([
                'tenant_id' => $tenant->getKey(),
                'user_id' => $user->getKey(),
                'role' => TenantRole::Owner,
                'status' => MembershipStatus::Active,
            ]);

            TenantSetting::create([
                'tenant_id' => $tenant->getKey(),
                'business_name' => $tenant->name,
                'locale' => 'pt_BR',
                'settings' => [],
            ]);

            // The company's public subdomain, derived from its name and unique across the platform.
            $this->subdomains->assign($tenant);

            // Every new company starts with the trial of the main plan.
            $this->subscriptions->startTrial($tenant, BillingSource::Company, $user);

            $this->auditLogger->record('tenant.created', $tenant, $tenant, [
                'segment_id' => $tenant->segment_id,
            ], $user);

            $this->auditLogger->record('tenant.owner_registered', $tenant, $user, [
                'role' => TenantRole::Owner->value,
            ], $user);

            return $user;
        });
    }

    /**
     * Create the company with a slug unique across tenants. Two registrations with the same name at the same moment
     * both see the slug as free; the unique index rejects the second insert, which then retries (inside a savepoint,
     * so the registration transaction survives) with a suffixed slug.
     *
     * @param  array{company_name: string, segment_id: int}  $data
     */
    private function createTenant(array $data): Tenant
    {
        $base = Str::slug($data['company_name']) ?: 'empresa';

        for ($attempt = 0; ; $attempt++) {
            $slug = $this->freeSlug($base, $attempt > 0);

            try {
                return DB::transaction(fn (): Tenant => Tenant::create([
                    'uuid' => (string) Str::uuid(),
                    'name' => $data['company_name'],
                    'slug' => $slug,
                    'segment_id' => $data['segment_id'],
                    'status' => TenantStatus::Active,
                    'timezone' => 'America/Sao_Paulo',
                ]));
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt >= 5) {
                    throw $exception;
                }
            }
        }
    }

    /**
     * The base slug when it is free and no suffix is forced, otherwise the base with a short random suffix.
     */
    private function freeSlug(string $base, bool $forceSuffix): string
    {
        $slug = $base;

        while ($forceSuffix || Tenant::query()->where('slug', $slug)->exists()) {
            $forceSuffix = false;
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
