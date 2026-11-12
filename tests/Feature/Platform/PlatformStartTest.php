<?php

use App\Enums\GrowthEventType;
use App\Models\Customer;
use App\Models\GrowthEvent;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\ServiceService;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
});

test('the public landing lists only active segments from the database', function () {
    Segment::factory()->create(['name' => 'Segmento Ativo Teste', 'active' => true]);
    Segment::factory()->create(['name' => 'Segmento Inativo Teste', 'active' => false]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('welcome')
            ->where('segments', fn ($segments) => collect($segments)->pluck('name')->contains('Segmento Ativo Teste')
                && ! collect($segments)->pluck('name')->contains('Segmento Inativo Teste')));
});

test('the landing call-to-actions point to real routes', function () {
    $this->get(route('register'))->assertOk();
    $this->get(route('login'))->assertOk();
    $this->get('/')->assertOk();
});

test('an authenticated visitor on the landing receives the session that the header uses to offer the panel', function () {
    // The landing is rendered by React, so the server response carries the Inertia props, not the button text.
    // The header shows "Ir para o painel" when auth.user is present (resources/js/components/landing/landing-header.tsx).
    $this->actingAs($this->owner)
        ->get('/')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('welcome')
            ->where('auth.user.id', $this->owner->id));
});

test('a visitor without a session receives no authenticated user on the landing', function () {
    $this->get('/')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('welcome')
            ->where('auth.user', null));
});

test('the landing sources never claim invented client counts, testimonials or guaranteed results', function () {
    $sources = collect(File::allFiles(resource_path('js/components/landing')))
        ->push(new SplFileInfo(resource_path('js/pages/welcome.tsx')))
        ->map(fn (SplFileInfo $file): string => File::get($file->getPathname()))
        ->implode("\n");

    expect($sources)->not->toContain('clientes atendidos')
        ->and($sources)->not->toContain('depoimento')
        ->and($sources)->not->toContain('garantido')
        ->and($sources)->toContain('Em desenvolvimento');
});

test('creating a customer, service or professional records one growth event for that company', function () {
    $service = app(ServiceService::class);

    app(CustomerService::class)->create($this->tenant, ['name' => 'Cliente Evento'], $this->owner);

    $service->create($this->tenant, ['name' => 'Serviço Evento'], $this->owner);

    expect(GrowthEvent::query()->where('tenant_id', $this->tenant->id)->where('event_type', GrowthEventType::CustomerCreated)->count())->toBe(1)
        ->and(GrowthEvent::query()->where('tenant_id', $this->tenant->id)->where('event_type', GrowthEventType::ServiceCreated)->count())->toBe(1);
});

test('growth events store a reference to the record, not personal data copied from it', function () {
    app(CustomerService::class)->create($this->tenant, ['name' => 'Nome Sensível', 'email' => 'sensivel@example.com'], $this->owner);

    $event = GrowthEvent::query()->where('event_type', GrowthEventType::CustomerCreated)->firstOrFail();

    expect($event->subject_type)->toBe((new Customer)->getMorphClass())
        ->and($event->subject_id)->toBe(Customer::query()->value('id'))
        ->and(json_encode($event->getAttributes()))->not->toContain('sensivel@example.com')
        ->and(json_encode($event->getAttributes()))->not->toContain('Nome Sensível');
});

test('growth events of one company are not visible to another company', function () {
    $other = Tenant::factory()->create();
    app(CustomerService::class)->create($other, ['name' => 'Outra empresa'], User::factory()->create());

    expect(GrowthEvent::query()->where('tenant_id', $this->tenant->id)->count())->toBe(0)
        ->and(GrowthEvent::query()->where('tenant_id', $other->id)->count())->toBe(1);
});
