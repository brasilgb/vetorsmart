<?php

use App\Enums\TenantStatus;
use App\Models\AssistantUsage;
use App\Models\AuditLog;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Database\Seeders\SegmentSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(SegmentSeeder::class);

    $this->root = User::factory()->rootAdmin()->withConfirmedTwoFactor()->create(['name' => 'Root ABrasil']);

    $this->segment = Segment::query()->where('slug', 'limpeza')->firstOrFail();
    $this->active = Tenant::factory()->create(['name' => 'Empresa Ativa', 'segment_id' => $this->segment->id]);
    $this->suspended = Tenant::factory()->suspended()->create(['name' => 'Empresa Suspensa']);

    $owner = User::factory()->create(['name' => 'Dona Ativa', 'email' => 'dona@example.com']);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->active->id, 'user_id' => $owner->id]);
});

test('guests are sent to login and common users get no console access', function () {
    $this->get('/rootadmin')->assertRedirect(route('login'));

    $common = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->active->id, 'user_id' => $common->id]);

    $this->actingAs($common);

    foreach (['/rootadmin', '/rootadmin/tenants', "/rootadmin/tenants/{$this->active->id}", '/rootadmin/segments', '/rootadmin/users', '/rootadmin/audit'] as $path) {
        $this->get($path)->assertForbidden();
    }

    $this->post("/rootadmin/tenants/{$this->active->id}/status", ['status' => 'suspended', 'confirm' => true])->assertForbidden();
    $this->post('/rootadmin/segments', ['name' => 'Indevido'])->assertForbidden();

    expect($this->active->fresh()->status)->toBe(TenantStatus::Active);
});

test('the console dashboard reports real totals and distribution', function () {
    $this->actingAs($this->root)
        ->get('/rootadmin')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rootadmin/dashboard')
            ->where('totals.tenants', 2)
            ->where('totals.active', 1)
            ->where('totals.suspended', 1)
            ->where('totals.users', 2)
            ->has('recent', 2)
            ->has('distribution'));
});

test('the company list filters by status and name', function () {
    $this->actingAs($this->root)
        ->get('/rootadmin/tenants?status=suspended')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rootadmin/tenants/index')
            ->has('tenants.data', 1)
            ->where('tenants.data.0.name', 'Empresa Suspensa'));

    $this->actingAs($this->root)
        ->get('/rootadmin/tenants?q=Ativa')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('tenants.data', 1));
});

test('the company detail shows administrative data and users without credentials', function () {
    $this->actingAs($this->root)
        ->get("/rootadmin/tenants/{$this->active->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rootadmin/tenants/show')
            ->where('tenant.name', 'Empresa Ativa')
            ->has('users.data', 1)
            ->missing('users.data.0.password')
            ->missing('users.data.0.remember_token')
            ->where('users.data.0.email', 'dona@example.com'));
});

test('suspending a company requires explicit confirmation and is audited', function () {
    $this->actingAs($this->root)
        ->post("/rootadmin/tenants/{$this->active->id}/status", ['status' => 'suspended'])
        ->assertSessionHasErrors('confirm');

    expect($this->active->fresh()->status)->toBe(TenantStatus::Active);

    $this->actingAs($this->root)
        ->post("/rootadmin/tenants/{$this->active->id}/status", ['status' => 'suspended', 'confirm' => true])
        ->assertRedirect();

    expect($this->active->fresh()->status)->toBe(TenantStatus::Suspended);

    $entry = AuditLog::query()->where('action', 'tenant.suspended')->firstOrFail();

    expect($entry->user_id)->toBe($this->root->id)
        ->and($entry->tenant_id)->toBe($this->active->id);
});

test('a suspended company can be activated again and the action is audited', function () {
    $this->actingAs($this->root)
        ->post("/rootadmin/tenants/{$this->suspended->id}/status", ['status' => 'active'])
        ->assertRedirect();

    expect($this->suspended->fresh()->status)->toBe(TenantStatus::Active)
        ->and(AuditLog::query()->where('action', 'tenant.activated')->where('tenant_id', $this->suspended->id)->exists())->toBeTrue();
});

test('companies are never deleted from the console', function () {
    $this->actingAs($this->root)
        ->delete("/rootadmin/tenants/{$this->active->id}")
        ->assertStatus(405);

    expect(Tenant::query()->whereKey($this->active->id)->exists())->toBeTrue();
});

test('the root admin manages segments: create, edit and deactivate, never delete', function () {
    $this->actingAs($this->root)
        ->post('/rootadmin/segments', ['name' => 'Piscinas', 'description' => 'Manutenção de piscinas'])
        ->assertRedirect();

    $segment = Segment::query()->where('name', 'Piscinas')->firstOrFail();

    $this->actingAs($this->root)
        ->patch("/rootadmin/segments/{$segment->id}", ['name' => 'Piscinas e spas', 'active' => false])
        ->assertRedirect();

    expect($segment->fresh())->name->toBe('Piscinas e spas')
        ->and($segment->fresh()->active)->toBeFalse();

    $this->actingAs($this->root)
        ->delete("/rootadmin/segments/{$segment->id}")
        ->assertStatus(405);

    expect(Segment::query()->whereKey($segment->id)->exists())->toBeTrue();
});

test('the users list is read-only and never exposes passwords', function () {
    $this->actingAs($this->root)
        ->get('/rootadmin/users')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rootadmin/users/index')
            ->has('memberships.data', 1)
            ->missing('memberships.data.0.password')
            ->where('memberships.data.0.role', 'owner'));
});

test('the audit trail is paginated, filterable and free of secrets', function () {
    $this->actingAs($this->root)
        ->post("/rootadmin/tenants/{$this->active->id}/status", ['status' => 'suspended', 'confirm' => true]);

    $this->actingAs($this->root)
        ->get('/rootadmin/audit?action=tenant.suspended')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rootadmin/audit/index')
            ->has('entries.data', 1)
            ->where('entries.data.0.action', 'tenant.suspended')
            ->where('entries.data.0.user', 'Root ABrasil'));

    $serialized = json_encode(AuditLog::query()->get()->pluck('metadata'));

    expect($serialized)->not->toContain('password')
        ->and($serialized)->not->toContain('token');
});

test('the root admin without a company lands on the console, not the tenant area', function () {
    $this->actingAs($this->root)
        ->get('/dashboard')
        ->assertRedirect('/rootadmin');
});

test('the dashboard reports AI consumption of the current month and lists unavailable indicators', function () {
    AssistantUsage::query()->create([
        'tenant_id' => $this->active->id,
        'usage_date' => now()->toDateString(),
        'messages' => 4,
        'provider_calls' => 2,
        'provider_failures' => 1,
        'tokens' => 900,
    ]);

    AssistantUsage::query()->create([
        'tenant_id' => $this->suspended->id,
        'usage_date' => now()->subMonths(2)->startOfMonth()->toDateString(),
        'messages' => 50,
        'provider_calls' => 50,
        'provider_failures' => 0,
        'tokens' => 50000,
    ]);

    $this->actingAs($this->root)
        ->get('/rootadmin')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rootadmin/dashboard')
            ->where('ai.messages', 4)
            ->where('ai.provider_calls', 2)
            ->where('ai.provider_failures', 1)
            ->where('ai.tokens', 900)
            ->where('sites.published', 0)
            ->where('appointments.upcoming', 0)
            ->has('pending', 5));
});

test('the company detail shows operational state and its administrative history', function () {
    $this->actingAs($this->root)
        ->post("/rootadmin/tenants/{$this->active->id}/status", ['status' => 'suspended', 'confirm' => true]);

    $this->actingAs($this->root)
        ->get("/rootadmin/tenants/{$this->active->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rootadmin/tenants/show')
            ->where('operations.active_users', 1)
            ->where('operations.primary_domain', null)
            ->where('operations.website', null)
            ->where('operations.assistant_enabled', false)
            ->has('history', 1)
            ->where('history.0.action', 'tenant.suspended')
            ->where('history.0.user', 'Root ABrasil'));
});
