<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Removes only homologation records: companies with the internal homologation mark
 * (tenants.is_homolog, set by homolog:mark) and users whose e-mail follows the
 * homologation pattern and who are left without any company after that. Platform
 * administrators are never removed. Every other record is untouched.
 */
class HomologPurgeService
{
    /**
     * Name prefix reserved for homologation. The public registration refuses it, so no customer can look like test data.
     */
    public const string NAME_PREFIX = '[HOMOLOG]';

    public const string EMAIL_PATTERN = 'homolog.%@example.test';

    /**
     * Every table with company rows, children before parents, so each delete respects the foreign keys without
     * relying on cascades or disabling the checks. The preview counts the same list, so it always matches the removal.
     * Audit entries are not listed: they are kept and lose the company reference (nullOnDelete).
     *
     * @var list<string>
     */
    private const array TENANT_TABLES = [
        'assistant_operations',
        'assistant_messages',
        'assistant_conversations',
        'assistant_usage',
        'assistant_settings',
        'appointments',
        'service_request_events',
        'service_requests',
        'professional_blocks',
        'website_versions',
        'tenant_websites',
        'tenant_domains',
        'professional_services',
        'professional_availabilities',
        'professionals',
        'services',
        'service_categories',
        'customers',
        'growth_events',
        'tenant_notifications',
        'notification_deliveries',
        'billing_charges',
        'subscription_events',
        'subscriptions',
        'tenant_settings',
        'tenant_users',
    ];

    /**
     * Describe what would be removed, without changing anything.
     *
     * @return array{tenant_ids: list<int>, counts: array<string, int>, user_ids: list<int>}
     */
    public function plan(): array
    {
        $tenantIds = array_values(Tenant::query()
            ->where('is_homolog', true)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all());

        $counts = ['tenants' => count($tenantIds)];

        foreach (self::TENANT_TABLES as $table) {
            $counts[$table] = $this->count($table, $tenantIds);
        }

        $counts['audit_entries_kept'] = $this->count('audit_logs', $tenantIds);

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

            if ($tenantIds !== []) {
                // Release the publication pointer first, so the snapshot rows can be removed.
                DB::table('tenant_websites')->whereIn('tenant_id', $tenantIds)->update(['published_version_id' => null]);

                foreach (self::TENANT_TABLES as $table) {
                    DB::table($table)->whereIn('tenant_id', $tenantIds)->delete();
                }

                Tenant::query()->whereIn('id', $tenantIds)->where('is_homolog', true)->delete();
            }

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
    private function count(string $table, array $tenantIds): int
    {
        if ($tenantIds === []) {
            return 0;
        }

        return DB::table($table)->whereIn('tenant_id', $tenantIds)->count();
    }

    /**
     * Homologation users with no membership outside the companies being removed. A platform administrator is never
     * included, even with a homologation e-mail.
     *
     * @param  list<int>  $tenantIds
     * @return list<int>
     */
    private function orphanHomologUsers(array $tenantIds): array
    {
        return array_values(User::query()
            ->where('email', 'like', self::EMAIL_PATTERN)
            ->where('is_root_admin', false)
            ->whereDoesntHave('memberships', fn ($query) => $query->whereNotIn('tenant_id', $tenantIds))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all());
    }
}
