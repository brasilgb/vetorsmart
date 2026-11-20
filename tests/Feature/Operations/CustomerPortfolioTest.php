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

/**
 * OPERATIONS-02: customer portfolio and customer page, from the company's own requests and appointments.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id, 'role' => 'operator']);
    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação']);
    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
});

function portfolioCustomer(Tenant $tenant, array $attributes = []): Customer
{
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id, ...$attributes]);

    if (array_key_exists('created_at', $attributes)) {
        $customer->forceFill(['created_at' => $attributes['created_at']])->save();
    }

    return $customer;
}

function portfolioRequest(Customer $customer, RequestStatus $status, array $attributes = []): ServiceRequest
{
    return ServiceRequest::factory()->create(['tenant_id' => $customer->tenant_id, 'customer_id' => $customer->id, 'status' => $status, ...$attributes]);
}

/**
 * @return array<int, array<string, mixed>>
 */
function portfolioRows(User $user, string $query = ''): array
{
    return collect(test()->actingAs($user)->get('/clientes'.$query)->assertOk()->viewData('page')['props']['customers']['data'])->keyBy('name')->all();
}

test('each customer shows completed services, last service, what is ahead and the recorded value, cancellations excluded', function () {
    $maria = portfolioCustomer($this->tenant, ['name' => 'Maria']);
    portfolioRequest($maria, RequestStatus::Completed, ['closed_at' => now()->subDays(10), 'realized_amount_cents' => 15000, 'service_id' => $this->service->id]);
    portfolioRequest($maria, RequestStatus::Completed, ['closed_at' => now()->subDays(40)]);
    portfolioRequest($maria, RequestStatus::Cancelled, ['closed_at' => now()->subDays(5), 'realized_amount_cents' => null]);
    $scheduled = portfolioRequest($maria, RequestStatus::Scheduled, ['service_id' => $this->service->id]);
    Appointment::factory()->create(['tenant_id' => $this->tenant->id, 'service_request_id' => $scheduled->id, 'professional_id' => $this->professional->id, 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour(), 'status' => AppointmentStatus::Scheduled]);

    $row = portfolioRows($this->owner)['Maria'];

    expect($row)->toMatchArray(['completed' => 2, 'recurring' => true, 'open' => 1, 'revenue' => 'R$ 150,00'])
        ->and($row['last_service'])->toBe($this->tenant->toLocal(now()->subDays(10))->format('d/m/Y'))
        ->and($row['next_appointment'])->not->toBeNull();
});

test('operators see the portfolio but not the recorded values', function () {
    $maria = portfolioCustomer($this->tenant, ['name' => 'Maria']);
    portfolioRequest($maria, RequestStatus::Completed, ['closed_at' => now(), 'realized_amount_cents' => 15000]);

    $page = $this->actingAs($this->operator)->get('/clientes')->viewData('page')['props'];
    expect($page['financial'])->toBeFalse()->and($page['customers']['data'][0]['revenue'])->toBeNull();

    $this->actingAs($this->operator)->get("/clientes/{$maria->id}")->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('financial', false)->where('summary.revenue', null)->where('summary.completed', 1));
});

test('relationship filters and indicators follow the period, and recurrence comes from completed services', function () {
    $new = portfolioCustomer($this->tenant, ['name' => 'Nova', 'created_at' => now()->subDays(10)]);
    $recurring = portfolioCustomer($this->tenant, ['name' => 'Recorrente', 'created_at' => now()->subDays(200)]);
    portfolioRequest($recurring, RequestStatus::Completed, ['closed_at' => now()->subDays(5)]);
    portfolioRequest($recurring, RequestStatus::Completed, ['closed_at' => now()->subDays(100)]);
    $requestsOnly = portfolioCustomer($this->tenant, ['name' => 'Só pedidos', 'created_at' => now()->subDays(200)]);
    portfolioRequest($requestsOnly, RequestStatus::Requested);
    portfolioRequest($requestsOnly, RequestStatus::Cancelled, ['closed_at' => now()->subDays(3)]);
    $quiet = portfolioCustomer($this->tenant, ['name' => 'Quieta', 'created_at' => now()->subDays(200)]);
    portfolioRequest($quiet, RequestStatus::Completed, ['closed_at' => now()->subDays(45)]);

    $names = fn (string $segment, int $days): array => array_keys(portfolioRows($this->owner, "?relacao={$segment}&dias={$days}"));

    expect($names('novos', 30))->toBe(['Nova'])
        ->and($names('recorrentes', 30))->toBe(['Recorrente'])
        ->and($names('em-aberto', 30))->toBe(['Só pedidos'])
        // Quiet for 30 days, but not for 60: the period gives the context.
        ->and($names('sem-atendimento', 30))->toBe(['Quieta'])
        ->and($names('sem-atendimento', 60))->toBe([]);

    $this->actingAs($this->owner)->get('/clientes?dias=30')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('indicators', ['novos' => 1, 'recorrentes' => 1, 'sem-atendimento' => 1, 'em-aberto' => 1, 'agendados' => 0]));

    // An unknown period or segment falls back to the defaults instead of failing.
    $this->actingAs($this->owner)->get('/clientes?dias=7&relacao=todos')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->where('filters.dias', 30)->where('filters.relacao', ''));
});

test('search still finds by name, e-mail and phone digits together with the figures', function () {
    portfolioCustomer($this->tenant, ['name' => 'Maria Souza', 'phone' => '51999990000']);
    portfolioCustomer($this->tenant, ['name' => 'João', 'email' => 'joao@example.com']);

    expect(array_keys(portfolioRows($this->owner, '?q=9999')))->toBe(['Maria Souza'])
        ->and(array_keys(portfolioRows($this->owner, '?q=joao@')))->toBe(['João']);
});

test('the customer page shows summary, frequency, contact links, what is ahead and the history', function () {
    $maria = portfolioCustomer($this->tenant, ['name' => 'Maria', 'phone' => '51999990000', 'email' => 'maria@example.com']);
    portfolioRequest($maria, RequestStatus::Completed, ['closed_at' => now()->subDays(60), 'realized_amount_cents' => 10000, 'service_id' => $this->service->id]);
    portfolioRequest($maria, RequestStatus::Completed, ['closed_at' => now()->subDays(30), 'service_id' => $this->service->id]);
    portfolioRequest($maria, RequestStatus::Completed, ['closed_at' => now(), 'realized_amount_cents' => 5050, 'service_id' => $this->service->id]);
    portfolioRequest($maria, RequestStatus::NotPerformed, ['closed_at' => now()->subDays(2), 'closing_reason' => 'Cliente ausente.']);

    $this->actingAs($this->owner)->get("/clientes/{$maria->id}")->assertOk()->assertInertia(fn (AssertableInertia $page) => $page
        ->component('tenant/customers/show')
        ->where('summary.completed', 3)
        ->where('summary.cancelled', 1)
        ->where('summary.requests', 4)
        ->where('summary.revenue', 'R$ 150,50')
        ->where('summary.revenue_unknown', 1)
        ->where('summary.frequency_days', 30)
        ->where('summary.last_service.service', 'Instalação')
        ->where('customer.contact', [
            ['kind' => 'phone', 'label' => 'Ligar', 'href' => 'tel:+5551999990000'],
            ['kind' => 'whatsapp', 'label' => 'WhatsApp', 'href' => 'https://wa.me/5551999990000'],
            ['kind' => 'email', 'label' => 'E-mail', 'href' => 'mailto:maria@example.com'],
        ])
        ->has('history.data', 4));
});

test('a customer of another company cannot be opened, and its records never count here', function () {
    $other = Tenant::factory()->create();
    $foreign = portfolioCustomer($other, ['name' => 'Alheia']);
    portfolioRequest($foreign, RequestStatus::Completed, ['closed_at' => now(), 'realized_amount_cents' => 99900]);
    portfolioCustomer($this->tenant, ['name' => 'Minha']);

    $this->actingAs($this->owner)->get("/clientes/{$foreign->id}")->assertNotFound();
    expect(array_keys(portfolioRows($this->owner)))->toBe(['Minha']);
});

test('the request screens link to the customer page', function () {
    $maria = portfolioCustomer($this->tenant, ['name' => 'Maria']);
    $request = portfolioRequest($maria, RequestStatus::Requested);

    $this->actingAs($this->operator)->get('/solicitacoes')->assertInertia(fn (AssertableInertia $page) => $page->where('requests.data.0.customer_id', $maria->id));
    $this->actingAs($this->operator)->get("/solicitacoes/{$request->id}")->assertInertia(fn (AssertableInertia $page) => $page->where('request.customer_id', $maria->id));
    $this->actingAs($this->operator)->get("/clientes/{$maria->id}")->assertOk();
});
