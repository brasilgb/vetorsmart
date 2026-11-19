<?php

use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\PortalListing;
use App\Models\ServiceRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\SegmentSeeder;
use Database\Seeders\VetorSmartDemoSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

/**
 * SEED-DEMO-01: demonstration base built through the real workflow, only where allowed, never twice.
 */
beforeEach(function () {
    $this->seed(SegmentSeeder::class);
    config(['platform.demo_password' => 'demo-local-password-123']);
});

test('the demo companies are created with real history, coherent revenue and a public listed site', function () {
    $this->seed(VetorSmartDemoSeeder::class);

    $tenant = Tenant::query()->where('slug', 'silva-eletrica')->sole();
    $completed = ServiceRequest::query()->forTenant($tenant)->where('status', RequestStatus::Completed)->get();

    expect(Tenant::query()->whereIn('slug', ['silva-eletrica', 'climasul', 'autotech'])->count())->toBe(3)
        ->and($completed)->toHaveCount(6)
        ->and($completed->first()->events()->pluck('to_status')->filter()->values()->all())->toBe(['requested', 'triaged', 'scheduled', 'in_progress', 'completed'])
        ->and(ServiceRequest::query()->where('status', RequestStatus::Cancelled)->whereNotNull('realized_amount_cents')->exists())->toBeFalse()
        ->and(PortalListing::query()->count())->toBe(3)
        ->and(Subscription::query()->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);

    $this->actingAs(User::query()->where('email', 'owner@silva-eletrica.example.test')->sole())
        ->get('/meu-crescimento?periodo=3-meses')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('current.completed', 6)
            ->where('current.cancelled', 2)
            ->where('current.revenue', 'R$ 1.535,00')
            ->where('current.revenue_unknown', 2));

    $this->get('/eletricistas/silva-eletrica')->assertOk();
});

test('running it again duplicates nothing', function () {
    $this->seed(VetorSmartDemoSeeder::class);
    $counts = fn (): array => [Tenant::query()->count(), User::query()->count(), ServiceRequest::query()->count(), Appointment::query()->count()];
    $first = $counts();

    $this->seed(VetorSmartDemoSeeder::class);

    expect($counts())->toBe($first);
});

test('records never cross companies', function () {
    $this->seed(VetorSmartDemoSeeder::class);

    expect(ServiceRequest::query()->join('customers', 'customers.id', '=', 'service_requests.customer_id')->whereColumn('customers.tenant_id', '!=', 'service_requests.tenant_id')->count())->toBe(0)
        ->and(Appointment::query()->join('service_requests', 'service_requests.id', '=', 'appointments.service_request_id')->whereColumn('service_requests.tenant_id', '!=', 'appointments.tenant_id')->count())->toBe(0);
});

test('it refuses production even with --force, and runs without a password never', function () {
    app()->detectEnvironment(fn () => 'production');

    try {
        expect(fn () => $this->artisan('db:seed', ['--class' => VetorSmartDemoSeeder::class, '--force' => true])->run())
            ->toThrow(RuntimeException::class, 'somente em ambiente local ou testing');
    } finally {
        app()->detectEnvironment(fn () => 'testing');
    }

    config(['platform.demo_password' => null]);
    expect(fn () => $this->seed(VetorSmartDemoSeeder::class))->toThrow(RuntimeException::class, 'VETORSMART_DEMO_PASSWORD')
        ->and(Tenant::query()->count())->toBe(0);
});

test('a company outside the demo set with the same slug is left untouched', function () {
    $foreign = Tenant::factory()->create(['slug' => 'climasul', 'name' => 'Outra Empresa']);

    $this->seed(VetorSmartDemoSeeder::class);

    expect($foreign->refresh()->name)->toBe('Outra Empresa')
        ->and(ServiceRequest::query()->forTenant($foreign)->count())->toBe(0)
        ->and(User::query()->where('email', 'owner@climasul.example.test')->exists())->toBeFalse();
});
