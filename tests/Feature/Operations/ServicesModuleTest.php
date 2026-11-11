<?php

use App\Enums\RequestStatus;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Carbon\CarbonImmutable;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
});

test('the services screen lists every service of the tenant, active and inactive', function () {
    Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação de ar']);
    Service::factory()->inactive()->create(['tenant_id' => $this->tenant->id, 'name' => 'Limpeza antiga']);

    $this->actingAs($this->owner)
        ->get(route('tenant.services.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tenant/services/index')
            ->has('services.data', 2));
});

test('a service is created with all its fields and persisted', function () {
    $this->actingAs($this->owner)
        ->post(route('tenant.services.store'), [
            'name' => 'Manutenção de split',
            'description' => 'Limpeza e carga de gás',
            'duration_minutes' => 90,
            'price_type' => 'fixed',
            'price' => '250.00',
            'active' => true,
        ])
        ->assertRedirect(route('tenant.services.index'));

    $service = Service::query()->where('name', 'Manutenção de split')->firstOrFail();

    expect($service->tenant_id)->toBe($this->tenant->id)
        ->and($service->duration_minutes)->toBe(90)
        ->and($service->price)->toBe('250.00')
        ->and($service->active)->toBeTrue();
});

test('the edit screen loads the saved service, so values survive a page reload', function () {
    $service = Service::factory()->fixed('120.00')->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 45]);

    $this->actingAs($this->owner)
        ->get(route('tenant.services.edit', $service))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tenant/services/form')
            ->where('service.id', $service->id)
            ->where('service.duration_minutes', 45)
            ->where('service.price_type', 'fixed'));
});

test('an update is persisted, and a fixed price without an amount is refused', function () {
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Antigo']);

    $this->actingAs($this->owner)
        ->patch(route('tenant.services.update', $service), ['name' => 'Novo nome', 'price_type' => 'fixed'])
        ->assertSessionHasErrors('price');

    $this->actingAs($this->owner)
        ->patch(route('tenant.services.update', $service), ['name' => 'Novo nome', 'price_type' => 'fixed', 'price' => '99.90'])
        ->assertRedirect();

    expect($service->fresh()->name)->toBe('Novo nome')
        ->and($service->fresh()->price)->toBe('99.90');
});

test('the triage screen sends the priority as its stored value, so the triage can be saved', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
    $request = ServiceRequest::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id, 'priority' => 'high']);

    $this->actingAs($this->owner)
        ->get(route('tenant.requests.show', $request))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('request.priority_value', 'high'));

    $this->actingAs($this->owner)
        ->patch(route('tenant.requests.triage', $request), ['service_id' => $service->id, 'priority' => 'high'])
        ->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(RequestStatus::Triaged);
});

test('a deactivated service disappears from the active filter and cannot be scheduled', function () {
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 60]);

    $this->actingAs($this->owner)->delete(route('tenant.services.deactivate', $service))->assertRedirect();

    expect($service->fresh()->active)->toBeFalse();

    $this->actingAs($this->owner)
        ->get(route('tenant.services.index', ['active' => '1']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('services.data', 0));
});

test('a service deactivated after triage cannot be booked', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 60]);
    $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $professional->services()->attach($service->id, ['tenant_id' => $this->tenant->id]);

    $monday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(9, 0);
    ProfessionalAvailability::create([
        'tenant_id' => $this->tenant->id,
        'professional_id' => $professional->id,
        'weekday' => 1,
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
    ]);

    $request = ServiceRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service->id,
        'status' => RequestStatus::Triaged,
    ]);

    $service->update(['active' => false]);

    $this->actingAs($this->owner)
        ->post(route('tenant.requests.schedule', $request), [
            'professional_id' => $professional->id,
            'starts_at' => $monday->format('Y-m-d H:i'),
        ])
        ->assertSessionHasErrors('service_id');

    expect($request->fresh()->status)->toBe(RequestStatus::Triaged);
});

test('professionals can be linked to a service from the service screen, and the link persists', function () {
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
    $ana = Professional::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Ana']);
    $bruno = Professional::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bruno']);

    $this->actingAs($this->owner)
        ->put(route('tenant.services.professionals', $service), ['professional_ids' => [$ana->id, $bruno->id]])
        ->assertRedirect();

    expect($service->professionals()->pluck('professionals.id')->sort()->values()->all())
        ->toBe([$ana->id, $bruno->id]);

    $this->actingAs($this->owner)
        ->get(route('tenant.services.edit', $service))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tenant/services/form')
            ->has('professionals', 2)
            ->where('linked_professional_ids', [$ana->id, $bruno->id]));
});

test('a service cannot be linked to a professional of another company', function () {
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id]);
    $otherTenant = Tenant::factory()->create();
    $foreign = Professional::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($this->owner)
        ->put(route('tenant.services.professionals', $service), ['professional_ids' => [$foreign->id]])
        ->assertSessionHasErrors('professional_ids');

    expect($service->professionals()->count())->toBe(0);
});

test('the search matches name and description, and the triage offers only active services', function () {
    Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação de ar', 'description' => 'Split e VRF']);
    Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Pintura']);

    $this->actingAs($this->owner)
        ->get(route('tenant.services.index', ['q' => 'split']))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('services.data', 1)->where('services.data.0.name', 'Instalação de ar'));

    Service::factory()->inactive()->create(['tenant_id' => $this->tenant->id, 'name' => 'Desativado']);

    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $request = ServiceRequest::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $customer->id]);

    $this->actingAs($this->owner)
        ->get(route('tenant.requests.show', $request))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tenant/requests/show')
            ->has('services', 2)
            ->where('services', fn ($services) => collect($services)->every(fn ($service) => $service['name'] !== 'Desativado')));
});

test('a service of another company cannot be edited', function () {
    $otherTenant = Tenant::factory()->create();
    $foreign = Service::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($this->owner)->get(route('tenant.services.edit', $foreign))->assertNotFound();
    $this->actingAs($this->owner)->patch(route('tenant.services.update', $foreign), ['name' => 'Invadido'])->assertNotFound();

    expect($foreign->fresh()->name)->not->toBe('Invadido');
});
