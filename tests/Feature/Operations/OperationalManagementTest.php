<?php

use App\Enums\NotificationCategory;
use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\ServiceRequestService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/**
 * INTEGRATION-02: responsible for each request, declared revenue of completed services, reliable notices and the
 * figures of Meu Crescimento.
 */
beforeEach(function () {
    Notification::fake();

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->admin = User::factory()->create(['name' => 'Ana Admin']);
    TenantUser::factory()->admin()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id]);
    $this->operator = User::factory()->create(['name' => 'Otávio Operador']);
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id, 'role' => 'operator']);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
});

function managedRequest(Tenant $tenant, Customer $customer, RequestStatus $status = RequestStatus::Requested, array $attributes = []): ServiceRequest
{
    return ServiceRequest::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'status' => $status, ...$attributes]);
}

test('an owner assigns and transfers the responsible, with history, audit and a notice to the new responsible only', function () {
    $request = managedRequest($this->tenant, $this->customer);

    $this->actingAs($this->owner)->patch("/solicitacoes/{$request->id}/responsavel", ['assigned_user_id' => $this->operator->id])->assertRedirect();
    $this->actingAs($this->owner)->patch("/solicitacoes/{$request->id}/responsavel", ['assigned_user_id' => $this->admin->id])->assertRedirect();

    $request->refresh();
    $events = $request->events()->where('type', 'assigned')->orderBy('id')->get();

    expect($request->assigned_user_id)->toBe($this->admin->id)
        ->and($request->assigned_at)->not->toBeNull()
        ->and($events->pluck('note')->all())->toBe(['Responsável: ninguém → Otávio Operador', 'Responsável: Otávio Operador → Ana Admin'])
        ->and($events->pluck('user_id')->unique()->all())->toBe([$this->owner->id])
        ->and(AuditLog::query()->where('action', 'service_request.assigned')->count())->toBe(2);

    $notices = TenantNotification::query()->forTenant($this->tenant)->where('category', NotificationCategory::RequestAssigned->value)->pluck('user_id')->sort()->values()->all();
    expect($notices)->toBe(collect([$this->operator->id, $this->admin->id])->sort()->values()->all());

    $this->actingAs($this->owner)->get("/solicitacoes/{$request->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('request.assignee', 'Ana Admin')->where('request.can_assign', true));
});

test('operators cannot choose the responsible', function () {
    $request = managedRequest($this->tenant, $this->customer);

    $this->actingAs($this->operator)->patch("/solicitacoes/{$request->id}/responsavel", ['assigned_user_id' => $this->operator->id])->assertForbidden();
    $this->actingAs($this->operator)->get("/solicitacoes/{$request->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('request.can_assign', false));

    expect($request->refresh()->assigned_user_id)->toBeNull();
});

test('the responsible must be an active member of the same company', function (string $case) {
    $request = managedRequest($this->tenant, $this->customer);
    $candidate = User::factory()->create();

    match ($case) {
        'other company' => TenantUser::factory()->owner()->create(['tenant_id' => Tenant::factory()->create()->id, 'user_id' => $candidate->id]),
        'inactive member' => TenantUser::factory()->inactive()->create(['tenant_id' => $this->tenant->id, 'user_id' => $candidate->id]),
        'no membership' => null,
    };

    $this->actingAs($this->owner)->patch("/solicitacoes/{$request->id}/responsavel", ['assigned_user_id' => $candidate->id])
        ->assertSessionHasErrors(['assigned_user_id' => 'Escolha uma pessoa ativa da equipe desta empresa.']);

    expect($request->refresh()->assigned_user_id)->toBeNull();
})->with(['other company', 'inactive member', 'no membership']);

test('a request of another company cannot be assigned nor have its amount recorded', function () {
    $other = Tenant::factory()->create();
    $foreign = managedRequest($other, Customer::factory()->create(['tenant_id' => $other->id]), RequestStatus::Completed);

    $this->actingAs($this->owner)->patch("/solicitacoes/{$foreign->id}/responsavel", ['assigned_user_id' => $this->operator->id])->assertNotFound();
    $this->actingAs($this->owner)->patch("/solicitacoes/{$foreign->id}/valor", ['realized_amount' => '100,00'])->assertNotFound();

    expect($foreign->refresh()->assigned_user_id)->toBeNull()->and($foreign->realized_amount_cents)->toBeNull();
});

test('the list shows and filters the responsible', function () {
    managedRequest($this->tenant, $this->customer, attributes: ['assigned_user_id' => $this->operator->id]);
    managedRequest($this->tenant, $this->customer, attributes: ['assigned_user_id' => $this->admin->id]);
    managedRequest($this->tenant, $this->customer);

    $this->actingAs($this->owner)->get("/solicitacoes?responsavel={$this->operator->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->has('requests.data', 1)->where('requests.data.0.assignee', 'Otávio Operador'));
    $this->actingAs($this->owner)->get('/solicitacoes?responsavel=nenhum')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('requests.data', 1)->where('requests.data.0.assignee', null));
    $this->actingAs($this->operator)->get('/solicitacoes?responsavel=meus')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('requests.data', 1));
});

test('the realized amount is recorded when completing and corrected only by owners and admins, with audit', function () {
    $request = managedRequest($this->tenant, $this->customer, RequestStatus::InProgress);

    $this->actingAs($this->operator)->post("/solicitacoes/{$request->id}/concluir", ['realized_amount' => '1.250,50'])->assertRedirect();
    expect($request->refresh()->status)->toBe(RequestStatus::Completed)->and($request->realized_amount_cents)->toBe(125050);

    $this->actingAs($this->operator)->patch("/solicitacoes/{$request->id}/valor", ['realized_amount' => '1,00'])->assertForbidden();
    $this->actingAs($this->admin)->patch("/solicitacoes/{$request->id}/valor", ['realized_amount' => '1.300,00'])->assertRedirect();

    expect($request->refresh()->realized_amount_cents)->toBe(130000)
        ->and($request->realized_amount_recorded_by)->toBe($this->admin->id)
        ->and($request->events()->where('type', 'realized_amount')->reorder('id', 'desc')->value('note'))->toBe('Valor realizado: R$ 1.300,00 (antes: R$ 1.250,50)')
        ->and(AuditLog::query()->where('action', 'service_request.realized_amount')->orderByDesc('id')->value('metadata'))->toMatchArray(['from_cents' => 125050, 'to_cents' => 130000]);
});

test('completing without an amount leaves it unknown, and operators may inform it once', function () {
    $request = managedRequest($this->tenant, $this->customer, RequestStatus::InProgress);

    $this->actingAs($this->operator)->post("/solicitacoes/{$request->id}/concluir")->assertRedirect();
    expect($request->refresh()->realized_amount_cents)->toBeNull();

    $this->actingAs($this->operator)->patch("/solicitacoes/{$request->id}/valor", ['realized_amount' => '0'])->assertRedirect();
    expect($request->refresh()->realized_amount_cents)->toBe(0);
});

test('cancelled or unperformed requests never take an amount', function (RequestStatus $status) {
    $request = managedRequest($this->tenant, $this->customer, $status);

    $this->actingAs($this->owner)->patch("/solicitacoes/{$request->id}/valor", ['realized_amount' => '500,00'])
        ->assertSessionHasErrors(['realized_amount' => 'Informe o valor realizado somente em atendimentos concluídos.']);

    expect($request->refresh()->realized_amount_cents)->toBeNull();
})->with([RequestStatus::Cancelled, RequestStatus::NotPerformed]);

test('an amount that is not in reais is refused', function () {
    $request = managedRequest($this->tenant, $this->customer, RequestStatus::Completed);

    $this->actingAs($this->owner)->patch("/solicitacoes/{$request->id}/valor", ['realized_amount' => '12,345'])
        ->assertSessionHasErrors(['realized_amount' => 'Informe o valor em reais, por exemplo 1.250,00.']);
});

test('amounts are parsed and formatted as integer cents', function (string $typed, ?int $cents) {
    expect(Money::parse($typed))->toBe($cents);
})->with([
    ['1.234,56', 123456], ['1234,5', 123450], ['1234.56', 123456], ['1.234', 123400], ['R$ 0,01', 1], ['0', 0],
    ['abc', null], ['-5,00', null], ['1,234', null], ['', null],
]);

test('Meu Crescimento sums known amounts by origin, keeps unknown apart and ignores cancellations', function () {
    $closed = now()->subMinutes(5);
    managedRequest($this->tenant, $this->customer, RequestStatus::Completed, ['origin' => RequestOrigin::Portal, 'closed_at' => $closed, 'realized_amount_cents' => 10050, 'assigned_user_id' => $this->operator->id]);
    managedRequest($this->tenant, $this->customer, RequestStatus::Completed, ['origin' => RequestOrigin::Portal, 'closed_at' => $closed, 'realized_amount_cents' => 20000]);
    managedRequest($this->tenant, $this->customer, RequestStatus::Completed, ['origin' => RequestOrigin::PublicSite, 'closed_at' => $closed]);
    managedRequest($this->tenant, $this->customer, RequestStatus::Cancelled, ['origin' => RequestOrigin::Portal, 'closed_at' => $closed]);
    $other = Tenant::factory()->create();
    managedRequest($other, Customer::factory()->create(['tenant_id' => $other->id]), RequestStatus::Completed, ['closed_at' => $closed, 'realized_amount_cents' => 999900]);

    $this->actingAs($this->owner)->get('/meu-crescimento')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('current.completed', 3)
            ->where('current.cancelled', 1)
            ->where('current.revenue', 'R$ 300,50')
            ->where('current.revenue_known', 2)
            ->where('current.revenue_unknown', 1)
            ->where('revenueByOrigin', fn ($rows) => collect($rows)->firstWhere('origin', 'portal')['revenue'] === 'R$ 300,50'
                && collect($rows)->firstWhere('origin', 'public_site')['revenue'] === null
                && collect($rows)->firstWhere('origin', 'public_site')['revenue_unknown'] === 1));

    $this->actingAs($this->owner)->get('/meu-crescimento?origem=public_site')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('current.completed', 1)->where('current.revenue', null)->where('current.revenue_unknown', 1));

    $this->actingAs($this->owner)->get("/meu-crescimento?responsavel={$this->operator->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('current.completed', 1)->where('current.revenue', 'R$ 100,50'));
});

test('a correction replaces the amount instead of counting the service twice', function () {
    $request = managedRequest($this->tenant, $this->customer, RequestStatus::Completed, ['closed_at' => now()->subMinutes(5)]);
    $service = app(ServiceRequestService::class);

    $service->recordRealizedAmount($request, 10000, $this->owner);
    $service->recordRealizedAmount($request, 15000, $this->owner);

    $this->actingAs($this->owner)->get('/meu-crescimento')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('current.revenue', 'R$ 150,00')->where('current.revenue_known', 1));
});

test('a failing notice does not block the assignment and is reprocessed once', function () {
    $request = managedRequest($this->tenant, $this->customer);
    TenantNotification::creating(fn () => throw new RuntimeException('panel down'));

    $this->actingAs($this->owner)->patch("/solicitacoes/{$request->id}/responsavel", ['assigned_user_id' => $this->operator->id])->assertRedirect();

    $delivery = DB::table('notification_deliveries')->where('event', 'request.assigned')->sole();
    expect($request->refresh()->assigned_user_id)->toBe($this->operator->id)
        ->and($delivery->status)->toBe('failed')
        ->and($delivery->last_error)->toBe(RuntimeException::class);

    TenantNotification::flushEventListeners();

    $this->artisan('notifications:reprocess')->assertSuccessful();
    $this->artisan('notifications:reprocess')->assertSuccessful();

    expect(DB::table('notification_deliveries')->where('event', 'request.assigned')->sole()->status)->toBe('processed')
        ->and(TenantNotification::query()->where('category', NotificationCategory::RequestAssigned->value)->where('user_id', $this->operator->id)->count())->toBe(1);
});

test('a failed assignment notice is skipped after a later transfer', function () {
    $request = managedRequest($this->tenant, $this->customer);
    TenantNotification::creating(fn () => throw new RuntimeException('panel down'));
    $this->actingAs($this->owner)->patch("/solicitacoes/{$request->id}/responsavel", ['assigned_user_id' => $this->operator->id]);
    TenantNotification::flushEventListeners();
    $this->actingAs($this->owner)->patch("/solicitacoes/{$request->id}/responsavel", ['assigned_user_id' => $this->admin->id]);

    $this->artisan('notifications:reprocess')->assertSuccessful();

    expect(DB::table('notification_deliveries')->where('event', 'request.assigned')->orderBy('id')->pluck('status')->all())->toBe(['skipped', 'processed'])
        ->and(TenantNotification::query()->where('category', NotificationCategory::RequestAssigned->value)->pluck('user_id')->all())->toBe([$this->admin->id]);
});

test('e-mails of a notice are counted as queued, never as sent in advance', function () {
    app(ServiceRequestService::class)->create($this->tenant, ['customer_id' => $this->customer->id, 'description' => 'Ar não gela'], null, RequestOrigin::PublicSite);

    $delivery = DB::table('notification_deliveries')->where('event', 'request.received')->sole();

    expect($delivery->status)->toBe('processed')
        ->and($delivery->mail_queued)->toBeGreaterThan(0)
        ->and($delivery->mail_sent)->toBe(0);
});
