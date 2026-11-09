<?php

use App\Enums\TenantStatus;
use App\Jobs\TenantScopedJob;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Tenancy\TenantContext;
use App\Services\Tenancy\TenantResolver;

/**
 * Test job that records which tenant it observed while running.
 */
class ProbeTenantJob extends TenantScopedJob
{
    /** @var list<int|null> */
    public static array $observedTenantIds = [];

    protected function handleForTenant(Tenant $tenant): void
    {
        self::$observedTenantIds[] = app(TenantContext::class)->tenantId();
    }
}

beforeEach(function () {
    ProbeTenantJob::$observedTenantIds = [];

    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->user->id]);
});

function runProbeJob(int $tenantId, int $userId): void
{
    (new ProbeTenantJob($tenantId, $userId))->handle(app(TenantResolver::class), app(TenantContext::class));
}

test('a job runs inside the tenant it was dispatched for', function () {
    runProbeJob($this->tenant->id, $this->user->id);

    expect(ProbeTenantJob::$observedTenantIds)->toBe([$this->tenant->id]);
});

test('a job does nothing when the tenant has been suspended since dispatch', function () {
    $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

    runProbeJob($this->tenant->id, $this->user->id);

    expect(ProbeTenantJob::$observedTenantIds)->toBe([]);
});

test('a job does nothing when the user lost membership since dispatch', function () {
    TenantUser::query()->where('user_id', $this->user->id)->delete();

    runProbeJob($this->tenant->id, $this->user->id);

    expect(ProbeTenantJob::$observedTenantIds)->toBe([]);
});

test('a job cannot run for a tenant the user does not belong to', function () {
    $otherTenant = Tenant::factory()->create();

    runProbeJob($otherTenant->id, $this->user->id);

    expect(ProbeTenantJob::$observedTenantIds)->toBe([]);
});

test('the previous tenant context is restored after a job', function () {
    $context = app(TenantContext::class);
    $context->set(TenantUser::query()->where('user_id', $this->user->id)->firstOrFail());

    runProbeJob($this->tenant->id, $this->user->id);

    expect($context->tenantId())->toBe($this->tenant->id);

    $context->forget();

    expect($context->tenant())->toBeNull();
});
