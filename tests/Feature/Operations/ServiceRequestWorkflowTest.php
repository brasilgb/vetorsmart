<?php

use App\Enums\AppointmentStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'duration_minutes' => 60]);
    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->professional->services()->attach($this->service->id, ['tenant_id' => $this->tenant->id]);

    // Monday, 08:00–12:00, in the future.
    $this->monday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(8, 0);
    ProfessionalAvailability::create([
        'tenant_id' => $this->tenant->id,
        'professional_id' => $this->professional->id,
        'weekday' => 1,
        'start_time' => '08:00:00',
        'end_time' => '12:00:00',
    ]);
});

function openRequest(Tenant $tenant, Customer $customer, ?Service $service = null): ServiceRequest
{
    return ServiceRequest::factory()->create([
        'tenant_id' => $tenant->id,
        'customer_id' => $customer->id,
        'service_id' => $service?->id,
        'status' => $service ? RequestStatus::Triaged : RequestStatus::Requested,
    ]);
}

test('a request goes from requested to triaged, scheduled, in progress and completed, with history', function () {
    $request = openRequest($this->tenant, $this->customer);

    $this->actingAs($this->owner)
        ->patch(route('tenant.requests.triage', $request), ['service_id' => $this->service->id])
        ->assertRedirect();

    $this->actingAs($this->owner)
        ->post(route('tenant.requests.schedule', $request), [
            'professional_id' => $this->professional->id,
            'starts_at' => $this->monday->format('Y-m-d H:i'),
        ])->assertRedirect();

    expect($request->fresh()->status)->toBe(RequestStatus::Scheduled)
        ->and(Appointment::query()->where('service_request_id', $request->id)->value('status'))->toBe(AppointmentStatus::Scheduled);

    $this->actingAs($this->owner)->post(route('tenant.requests.start', $request))->assertRedirect();
    $this->actingAs($this->owner)->post(route('tenant.requests.complete', $request))->assertRedirect();

    $request->refresh();

    expect($request->status)->toBe(RequestStatus::Completed)
        ->and($request->closed_at)->not->toBeNull()
        ->and(Appointment::query()->where('service_request_id', $request->id)->value('status'))->toBe(AppointmentStatus::Completed)
        ->and($request->events()->where('type', 'status_changed')->count())->toBe(4);
});

test('the request screen renders its history after the request has moved', function () {
    $request = openRequest($this->tenant, $this->customer, $this->service);

    $this->actingAs($this->owner)
        ->post(route('tenant.requests.cancel', $request), ['reason' => 'Teste de histórico'])
        ->assertRedirect();

    $this->actingAs($this->owner)
        ->get(route('tenant.requests.show', $request))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('tenant/requests/show')
            ->where('request.status', 'cancelled')
            ->where('events.0.user', $this->owner->name)
            ->where('events.0.to_status', 'Cancelado'));
});

test('an invalid transition is refused and leaves the request unchanged', function () {
    $request = openRequest($this->tenant, $this->customer);

    $this->actingAs($this->owner)
        ->post(route('tenant.requests.complete', $request))
        ->assertSessionHasErrors('status');

    expect($request->fresh()->status)->toBe(RequestStatus::Requested);
});

test('cancelling a scheduled request cancels its appointment', function () {
    $request = openRequest($this->tenant, $this->customer, $this->service);

    $this->actingAs($this->owner)->post(route('tenant.requests.schedule', $request), [
        'professional_id' => $this->professional->id,
        'starts_at' => $this->monday->format('Y-m-d H:i'),
    ]);

    $this->actingAs($this->owner)
        ->post(route('tenant.requests.cancel', $request), ['reason' => 'Cliente desistiu'])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($request->fresh()->closing_reason)->toBe('Cliente desistiu')
        ->and(Appointment::query()->where('service_request_id', $request->id)->value('status'))->toBe(AppointmentStatus::Cancelled);
});

test('a request cannot be cancelled without a reason', function () {
    $request = openRequest($this->tenant, $this->customer);

    $this->actingAs($this->owner)
        ->post(route('tenant.requests.cancel', $request), [])
        ->assertSessionHasErrors('reason');

    expect($request->fresh()->status)->toBe(RequestStatus::Requested);
});

test('a request of another company cannot be read or moved', function () {
    $otherTenant = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
    $foreign = openRequest($otherTenant, $otherCustomer);

    $this->actingAs($this->owner)->get(route('tenant.requests.show', $foreign))->assertNotFound();
    $this->actingAs($this->owner)->post(route('tenant.requests.cancel', $foreign), ['reason' => 'x'])->assertNotFound();

    expect($foreign->fresh()->status)->toBe(RequestStatus::Requested);
});

test('a customer or service of another company cannot be used in a new request', function () {
    $otherTenant = Tenant::factory()->create();
    $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($this->owner)
        ->post(route('tenant.requests.store'), ['customer_id' => $otherCustomer->id, 'description' => 'Teste'])
        ->assertSessionHasErrors('customer_id');
});

test('non-members cannot reach the operational screens', function () {
    $stranger = User::factory()->create();
    $request = openRequest($this->tenant, $this->customer);

    $this->actingAs($stranger)->get(route('tenant.requests.index'))->assertForbidden();
    $this->actingAs($stranger)->get(route('tenant.agenda.index'))->assertForbidden();
    $this->actingAs($stranger)->post(route('tenant.requests.cancel', $request), ['reason' => 'x'])->assertForbidden();
});
