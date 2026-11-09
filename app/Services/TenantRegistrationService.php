<?php

namespace App\Services;

use App\Enums\MembershipStatus;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates a company and its owner in one transaction. Either every step is
 * persisted or none is.
 */
final class TenantRegistrationService
{
    public function __construct(private AuditLogger $auditLogger) {}

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

            $tenant = Tenant::create([
                'uuid' => (string) Str::uuid(),
                'name' => $data['company_name'],
                'slug' => $this->uniqueSlug($data['company_name']),
                'segment_id' => $data['segment_id'],
                'status' => TenantStatus::Active,
                'timezone' => 'America/Sao_Paulo',
            ]);

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
     * Build a slug unique across tenants, appending a short suffix on collision.
     */
    private function uniqueSlug(string $companyName): string
    {
        $base = Str::slug($companyName) ?: 'empresa';
        $slug = $base;

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
