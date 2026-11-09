<?php

use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();
});

test('the database refuses a service that points to a category of another tenant', function () {
    $categoryB = ServiceCategory::factory()->create(['tenant_id' => $this->tenantB->id]);

    expect(fn () => DB::table('services')->insert([
        'tenant_id' => $this->tenantA->id,
        'service_category_id' => $categoryB->id,
        'name' => 'Cruzado',
        'price_type' => 'on_request',
        'requires_evaluation' => false,
        'active' => true,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('the database refuses a professional link that crosses tenants', function () {
    $professionalA = Professional::factory()->create(['tenant_id' => $this->tenantA->id]);
    $serviceB = Service::factory()->create(['tenant_id' => $this->tenantB->id]);

    expect(fn () => DB::table('professional_services')->insert([
        'tenant_id' => $this->tenantA->id,
        'professional_id' => $professionalA->id,
        'service_id' => $serviceB->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('the database refuses a second active document in the same tenant but allows it across tenants', function () {
    Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'document' => '12345678909']);

    expect(fn () => Customer::factory()->create(['tenant_id' => $this->tenantA->id, 'document' => '12345678909']))
        ->toThrow(QueryException::class);

    Customer::factory()->create(['tenant_id' => $this->tenantB->id, 'document' => '12345678909']);

    expect(Customer::query()->where('document', '12345678909')->count())->toBe(2);
});

test('the database refuses negative prices and zero durations', function () {
    expect(fn () => DB::table('services')->insert([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Negativo',
        'price' => -10,
        'price_type' => 'fixed',
        'requires_evaluation' => false,
        'active' => true,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('services')->insert([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Duração zero',
        'duration_minutes' => 0,
        'price_type' => 'on_request',
        'requires_evaluation' => false,
        'active' => true,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

test('the database refuses duplicate category names inside a tenant', function () {
    ServiceCategory::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Reparos']);

    expect(fn () => ServiceCategory::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Reparos']))
        ->toThrow(QueryException::class);
});

test('the operational tables use InnoDB and the expected foreign keys on MySQL-compatible servers', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql');

    $tables = ['customers', 'service_categories', 'services', 'professionals', 'professional_services'];

    foreach ($tables as $table) {
        $engine = DB::selectOne(
            'SELECT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table],
        )->engine;

        expect(strtolower((string) $engine))->toBe('innodb');
    }

    $compositeForeignKeys = DB::select(
        "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('services', 'professional_services')
         AND REFERENCED_TABLE_NAME IS NOT NULL AND COLUMN_NAME = 'tenant_id'",
    );

    expect(count($compositeForeignKeys))->toBeGreaterThanOrEqual(3);
});
