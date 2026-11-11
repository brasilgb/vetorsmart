<?php

use App\Enums\AppointmentStatus;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create(['name' => 'Dona Ana']);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação']);
    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Bruno']);
});

test('the customer screen lists the requests of that customer with cancellations and their reason', function () {
    $request = ServiceRequest::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'service_id' => $this->service->id,
        'status' => RequestStatus::Triaged,
    ]);

    Appointment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'service_request_id' => $request->id,
        'professional_id' => $this->professional->id,
        'status' => AppointmentStatus::Cancelled,
        'cancelling_reason' => 'Cliente viajou',
        'cancelled_at' => now(),
        'cancelled_by_user_id' => $this->owner->id,
    ]);

    $this->actingAs($this->owner)
        ->get(route('tenant.customers.edit', $this->customer))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tenant/customers/form')
            ->has('history.data', 1)
            ->where('history.data.0.id', $request->id)
            ->where('history.data.0.service', 'Instalação')
            ->where('history.data.0.appointments.0.professional', 'Bruno')
            ->where('history.data.0.appointments.0.cancelling_reason', 'Cliente viajou')
            ->where('history.data.0.appointments.0.cancelled_by', 'Dona Ana'));
});

test('the history never shows requests of another customer or another company', function () {
    $otherCustomer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    ServiceRequest::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $otherCustomer->id]);

    $otherTenant = Tenant::factory()->create();
    $foreignCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
    ServiceRequest::factory()->create(['tenant_id' => $otherTenant->id, 'customer_id' => $foreignCustomer->id]);

    $this->actingAs($this->owner)
        ->get(route('tenant.customers.edit', $this->customer))
        ->assertInertia(fn (AssertableInertia $page) => $page->has('history.data', 0));

    $this->actingAs($this->owner)
        ->get(route('tenant.customers.edit', $foreignCustomer))
        ->assertNotFound();
});

test('the history is paginated, most recent first', function () {
    foreach (range(1, 12) as $offset) {
        ServiceRequest::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'updated_at' => now()->subDays($offset),
        ]);
    }

    $newest = ServiceRequest::query()->where('customer_id', $this->customer->id)->orderByDesc('updated_at')->firstOrFail();

    $this->actingAs($this->owner)
        ->get(route('tenant.customers.edit', $this->customer))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('history.data', 10)
            ->where('history.data.0.id', $newest->id)
            ->where('history.total', 12));
});
