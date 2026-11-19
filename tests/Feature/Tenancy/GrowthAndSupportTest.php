<?php

use App\Enums\RequestStatus;
use App\Enums\SupportTicketStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Support\SupportTicketService;
use App\Support\PlatformTime;
use Carbon\CarbonInterface;
use Inertia\Testing\AssertableInertia;

/**
 * PANELS-07: Meu Crescimento and the Central de Suporte (company and platform sides), CSV exports.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Clima Total']);
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id]);

    $this->other = Tenant::factory()->create(['name' => 'Outra Empresa']);
    $this->otherOwner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->other->id, 'user_id' => $this->otherOwner->id]);

    $this->root = User::factory()->rootAdmin()->create();
});

function serviceRequests(Tenant $tenant, int $total, int $completed = 0, ?CarbonInterface $at = null): void
{
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $at ??= now();

    for ($index = 0; $index < $total; $index++) {
        $request = ServiceRequest::factory()->create([
            'tenant_id' => $tenant->id, 'customer_id' => $customer->id,
            'status' => $index < $completed ? RequestStatus::Completed : RequestStatus::Requested,
            'closed_at' => $index < $completed ? $at : null,
        ]);
        $request->forceFill(['created_at' => $at])->save();
    }
}

function companyTicket(Tenant $tenant, User $owner): SupportTicket
{
    test()->actingAs($owner)->post('/suporte', ['subject' => 'Agenda não abre', 'category' => 'technical', 'description' => 'A agenda fica carregando sem parar.']);

    return SupportTicket::query()->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
}

test('Meu Crescimento shows only the real figures of the company, with conversion and comparison', function () {
    serviceRequests($this->tenant, 6, 3);
    serviceRequests($this->tenant, 4, 1, now()->subMonthNoOverflow()->startOfMonth()->addDays(2));
    serviceRequests($this->other, 9, 9);

    $this->actingAs($this->owner)->get('/meu-crescimento')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('current.requests', 6)
            ->where('current.completed', 3)
            ->where('current.conversion', 50)
            ->where('previous.requests', 4)
            ->where('previous.conversion', null)
            ->where('comparison.requests', 50)
            ->where('comparison.conversion', null)
            ->where('hasActivity', true));
});

test('Meu Crescimento says when there is not enough data', function () {
    serviceRequests($this->tenant, 2, 1);

    $this->actingAs($this->owner)->get('/meu-crescimento')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('current.conversion', null)->where('comparison.requests', null));
});

test('operators do not see Meu Crescimento nor the Central de Suporte', function (string $path) {
    $this->actingAs($this->operator)->get($path)->assertForbidden();
})->with(['/meu-crescimento', '/suporte']);

test('a company opens a support request that belongs to it, with category and audit', function () {
    $this->actingAs($this->owner)->post('/suporte', ['subject' => 'Oi', 'category' => '', 'description' => 'curto'])
        ->assertSessionHasErrors(['subject' => 'O assunto precisa de ao menos 5 caracteres.', 'category' => 'Escolha uma categoria.', 'description' => 'A descrição precisa de ao menos 10 caracteres.']);

    $ticket = companyTicket($this->tenant, $this->owner);

    expect($ticket->opened_via)->toBe('tenant')
        ->and($ticket->category->value)->toBe('technical')
        ->and($ticket->priority->value)->toBe('normal')
        ->and(AuditLog::query()->where('action', 'support.ticket_opened')->sole()->tenant_id)->toBe($this->tenant->id);
});

test('a company never sees the requests of another company', function () {
    $foreign = companyTicket($this->other, $this->otherOwner);
    companyTicket($this->tenant, $this->owner);

    $this->actingAs($this->owner)->get('/suporte')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('tickets.data', fn ($rows) => collect($rows)->every(fn ($row) => $row['id'] !== $foreign->id)));
    $this->actingAs($this->owner)->get("/suporte/{$foreign->id}")->assertNotFound();
    $this->actingAs($this->owner)->post("/suporte/{$foreign->id}/respostas", ['note' => 'Intromissão'])->assertNotFound();

    expect($foreign->events()->count())->toBe(1);
});

test('internal notes of the platform team are never shown to the company', function () {
    $ticket = companyTicket($this->tenant, $this->owner);
    app(SupportTicketService::class)->platformUpdate($ticket, ['note' => 'Cliente com histórico de atraso.', 'internal' => true], $this->root);
    app(SupportTicketService::class)->platformUpdate($ticket, ['note' => 'Estamos verificando.'], $this->root);

    $response = $this->actingAs($this->owner)->get("/suporte/{$ticket->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('events', fn ($events) => collect($events)->pluck('note')->filter()->values()->all() === ['Estamos verificando.']));

    expect($response->getContent())->not->toContain('histórico de atraso');
});

test('a company reply reopens a request waiting for it or resolved, and a closed one refuses replies', function () {
    $ticket = companyTicket($this->tenant, $this->owner);
    $service = app(SupportTicketService::class);

    $service->platformUpdate($ticket, ['status' => SupportTicketStatus::Waiting], $this->root);
    $this->actingAs($this->owner)->post("/suporte/{$ticket->id}/respostas", ['note' => 'Segue o print.'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Open);

    $service->platformUpdate($ticket, ['status' => SupportTicketStatus::Resolved], $this->root);
    $this->actingAs($this->owner)->post("/suporte/{$ticket->id}/respostas", ['note' => 'Voltou a acontecer.'])->assertRedirect();
    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Open)
        ->and($ticket->fresh()->closed_at)->toBeNull();

    $service->platformUpdate($ticket, ['status' => SupportTicketStatus::Closed], $this->root);
    $this->actingAs($this->owner)->post("/suporte/{$ticket->id}/respostas", ['note' => 'Mais uma coisa.'])
        ->assertSessionHasErrors(['note' => 'Este chamado foi encerrado. Abra um novo chamado para continuar.']);
});

test('the platform assigns a responsible person and a due date, and overdue requests are counted and filtered', function () {
    $ticket = companyTicket($this->tenant, $this->owner);

    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/acompanhamentos", ['assigned_to' => $this->owner->id])
        ->assertSessionHasErrors(['assigned_to' => 'O responsável precisa ser um administrador da plataforma ativo.']);

    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/acompanhamentos", ['assigned_to' => $this->root->id, 'due_at' => now(PlatformTime::timezone())->addHour()->format('Y-m-d\TH:i')])->assertRedirect();
    expect($ticket->fresh()->assigned_to)->toBe($this->root->id)
        ->and($ticket->events()->whereIn('type', ['assigned', 'due'])->count())->toBe(2);

    $this->travel(2)->hours();

    $this->actingAs($this->root)->get('/rootadmin/suporte?status=atrasados')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('indicators.overdue', 1)
            ->where('tickets.data.0.id', $ticket->id)
            ->where('tickets.data.0.overdue', true));
});

test('status changes are controlled: a finished request only comes back through reopening, with a reason', function () {
    $ticket = companyTicket($this->tenant, $this->owner);
    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/acompanhamentos", ['status' => 'closed'])->assertRedirect();

    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/acompanhamentos", ['status' => 'in_progress'])
        ->assertSessionHasErrors('status');
    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/reabrir", ['reason' => ''])->assertSessionHasErrors('reason');

    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/reabrir", ['reason' => 'O problema voltou.'])->assertRedirect();

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Open)
        ->and(AuditLog::query()->where('action', 'support.ticket_reopened')->count())->toBe(1);
});

test('the CSV exports follow the filters, never carry e-mails and neutralize formulas', function () {
    $this->other->forceFill(['name' => '=HYPERLINK("http://x")'])->save();
    companyTicket($this->tenant, $this->owner);

    $tenants = $this->actingAs($this->root)->get('/rootadmin/tenants/exportar')->assertOk();
    expect($tenants->headers->get('content-type'))->toContain('text/csv')
        ->and($tenants->streamedContent())->toContain('Clima Total')
        ->and($tenants->streamedContent())->toContain("'=HYPERLINK")
        ->and($tenants->streamedContent())->not->toContain($this->owner->email);

    expect($this->actingAs($this->root)->get('/rootadmin/suporte/exportar?status=todos')->assertOk()->streamedContent())->toContain('Agenda não abre')
        ->and($this->actingAs($this->root)->get('/rootadmin/crescimento/exportar')->assertOk()->streamedContent())->toContain('Clima Total');
});

test('company users cannot reach the platform support nor the exports', function (string $path) {
    $this->actingAs($this->owner)->get($path)->assertForbidden();
})->with(['/rootadmin/tenants/exportar', '/rootadmin/suporte/exportar', '/rootadmin/crescimento/exportar']);
