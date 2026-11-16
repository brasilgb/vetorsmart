<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/*
 * The console shows platform records in America/Sao_Paulo (UTC-3) while instants stay in UTC. The clock is
 * set to 01:30 UTC on 17/11/2026, which is still 22:30 of 16/11/2026 in São Paulo, so a missing or doubled
 * conversion changes both the hour and the day.
 */

beforeEach(function () {
    $this->root = User::factory()->rootAdmin()->create();

    $this->travelTo(CarbonImmutable::parse('2026-11-17 01:30:00', 'UTC'));
});

test('an audit entry is stamped by the application clock in UTC', function () {
    $entry = app(AuditLogger::class)->record('tenant.suspended', null);

    expect(DB::table('audit_logs')->where('id', $entry->id)->value('created_at'))->toBe('2026-11-17 01:30:00');
});

test('the audit trail shows each entry once converted to the platform time zone', function () {
    app(AuditLogger::class)->record('tenant.suspended', null);

    $this->actingAs($this->root)
        ->get('/rootadmin/audit')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('rootadmin/audit/index')
            ->where('entries.data.0.created_at', '16/11/2026 22:30'));
});

test('the audit date filter selects a calendar day of the platform time zone', function () {
    app(AuditLogger::class)->record('inside.late_evening', null);

    $this->travelTo(CarbonImmutable::parse('2026-11-16 02:00:00', 'UTC'));
    app(AuditLogger::class)->record('outside.previous_day', null);

    $this->travelTo(CarbonImmutable::parse('2026-11-17 03:00:00', 'UTC'));
    app(AuditLogger::class)->record('outside.next_day', null);

    $this->actingAs($this->root)
        ->get('/rootadmin/audit?from=2026-11-16&to=2026-11-16')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('entries.data', 1)
            ->where('entries.data.0.action', 'inside.late_evening'));
});

test('a malformed audit date filter is ignored', function () {
    app(AuditLogger::class)->record('tenant.suspended', null);

    $this->actingAs($this->root)
        ->get('/rootadmin/audit?from=16/11/2026&to=2026-02-31')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->has('entries.data', 1));
});

test('company registration dates and company history use the platform time zone', function () {
    $tenant = Tenant::factory()->create();
    app(AuditLogger::class)->record('tenant.suspended', $tenant);

    $this->actingAs($this->root)
        ->get('/rootadmin/tenants')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tenants.data.0.created_at', '16/11/2026'));

    $this->actingAs($this->root)
        ->get("/rootadmin/tenants/{$tenant->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tenant.created_at', '16/11/2026')
            ->where('history.0.created_at', '16/11/2026 22:30'));

    $this->actingAs($this->root)
        ->get('/rootadmin')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('recent.0.created_at', '16/11/2026'));
});
