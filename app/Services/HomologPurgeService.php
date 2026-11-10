<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Removes only homologation records: companies whose name starts with "[HOMOLOG]"
 * and users whose e-mail follows the homologation pattern and who are left without
 * any company after that. Every other record is untouched.
 */
class HomologPurgeService
{
    public const string NAME_PREFIX = '[HOMOLOG]';

    public const string EMAIL_PATTERN = 'homolog.%@example.test';

    /**
     * Describe what would be removed, without changing anything.
     *
     * @return array{tenant_ids: list<int>, counts: array<string, int>, user_ids: list<int>}
     */
    public function plan(): array
    {
        $tenantIds = array_values(Tenant::query()
            ->where('name', 'like', self::NAME_PREFIX.'%')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all());

        $counts = [
            'tenants' => count($tenantIds),
            'tenant_users' => $this->count('tenant_users', 'tenant_id', $tenantIds),
            'tenant_settings' => $this->count('tenant_settings', 'tenant_id', $tenantIds),
            'customers' => $this->count('customers', 'tenant_id', $tenantIds),
            'service_categories' => $this->count('service_categories', 'tenant_id', $tenantIds),
            'services' => $this->count('services', 'tenant_id', $tenantIds),
            'professionals' => $this->count('professionals', 'tenant_id', $tenantIds),
            'professional_services' => $this->count('professional_services', 'tenant_id', $tenantIds),
            'professional_availabilities' => $this->count('professional_availabilities', 'tenant_id', $tenantIds),
            'growth_events' => $this->count('growth_events', 'tenant_id', $tenantIds),
            'tenant_domains' => $this->count('tenant_domains', 'tenant_id', $tenantIds),
            'tenant_websites' => $this->count('tenant_websites', 'tenant_id', $tenantIds),
            'website_versions' => $this->count('website_versions', 'tenant_id', $tenantIds),
            'audit_entries_kept' => $this->count('audit_logs', 'tenant_id', $tenantIds),
        ];

        $userIds = $this->orphanHomologUsers($tenantIds);

        return [
            'tenant_ids' => $tenantIds,
            'counts' => [...$counts, 'users' => count($userIds)],
            'user_ids' => $userIds,
        ];
    }

    /**
     * Remove the planned records inside one transaction, in dependency order.
     * Audit entries are kept: their tenant reference becomes null, never deleted.
     *
     * @return array<string, int>
     */
    public function execute(): array
    {
        $plan = $this->plan();

        if ($plan['tenant_ids'] === [] && $plan['user_ids'] === []) {
            return ['tenants' => 0, 'users' => 0];
        }

        return DB::transaction(function () use ($plan): array {
            $tenantIds = $plan['tenant_ids'];

            // Release the publication pointer first, so the snapshot rows can be removed.
            DB::table('tenant_websites')->whereIn('tenant_id', $tenantIds)->update(['published_version_id' => null]);
            DB::table('website_versions')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('tenant_websites')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('tenant_domains')->whereIn('tenant_id', $tenantIds)->delete();

            DB::table('professional_services')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('professional_availabilities')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('professionals')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('services')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('service_categories')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('customers')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('growth_events')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('tenant_settings')->whereIn('tenant_id', $tenantIds)->delete();
            DB::table('tenant_users')->whereIn('tenant_id', $tenantIds)->delete();
            Tenant::query()->whereIn('id', $tenantIds)->delete();

            DB::table('users')->whereIn('id', $plan['user_ids'])->delete();

            return [
                'tenants' => count($tenantIds),
                'users' => count($plan['user_ids']),
            ];
        });
    }

    /**
     * @param  list<int>  $tenantIds
     */
    private function count(string $table, string $column, array $tenantIds): int
    {
        if ($tenantIds === []) {
            return 0;
        }

        return DB::table($table)->whereIn($column, $tenantIds)->count();
    }

    /**
     * Homologation users with no membership outside the companies being removed.
     *
     * @param  list<int>  $tenantIds
     * @return list<int>
     */
    private function orphanHomologUsers(array $tenantIds): array
    {
        return array_values(User::query()
            ->where('email', 'like', self::EMAIL_PATTERN)
            ->whereDoesntHave('memberships', fn ($query) => $query->whereNotIn('tenant_id', $tenantIds))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }
}
