<?php

use App\Enums\RequestStatus;
use App\Enums\SupportTicketStatus;
use App\Enums\TenantStatus;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ServiceRequest;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

/**
 * PANELS-06: Configurações Globais (read-only, no secret), Crescimento e SEO (real figures) and Suporte operacional.
 */
beforeEach(function () {
    $this->root = User::factory()->rootAdmin()->create();
    $this->tenant = Tenant::factory()->create(['name' => 'Clima Total']);
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
});

/**
 * Requests of the tenant created now; the first $completed are completed in the current month.
 */
function requestsFor(Tenant $tenant, int $total, int $completed = 0): void
{
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);

    for ($index = 0; $index < $total; $index++) {
        ServiceRequest::factory()->create([
            'tenant_id' => $tenant->id,
            'customer_id' => $customer->id,
            'status' => $index < $completed ? RequestStatus::Completed : RequestStatus::Requested,
            'closed_at' => $index < $completed ? now() : null,
        ]);
    }
}

test('Configurações Globais shows the parameters in force and never a secret', function () {
    BillingSetting::current()->forceFill(['efi_pix_client_secret' => 'segredo-pix-xyz', 'efi_charges_client_secret' => 'segredo-cobrancas-xyz', 'webhook_secret' => 'segredo-webhook-xyz'])->save();
    config(['assistant.provider_api_key' => 'chave-ia-xyz', 'mail.mailers.smtp.password' => 'senha-smtp-xyz']);

    $response = $this->actingAs($this->root)->get('/rootadmin/configuracoes')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('sections.0.title', 'Plataforma')
            ->where('sections.1.items', fn ($items) => collect($items)->firstWhere('label', 'Cobrança em vigor')['state'] === 'disabled')
            ->where('sections.2.items', fn ($items) => collect($items)->firstWhere('label', 'Efí: API Pix')['state'] === 'unavailable'));

    expect($response->getContent())->not->toContain('segredo-')
        ->and($response->getContent())->not->toContain('chave-ia-xyz')
        ->and($response->getContent())->not->toContain('senha-smtp-xyz');
});

test('Crescimento e SEO counts the real activity of the month and shows conversion only with enough requests', function () {
    requestsFor($this->tenant, 6, 3);
    $other = Tenant::factory()->create(['name' => 'Outra Empresa']);
    requestsFor($other, 2, 1);
    $request = ServiceRequest::query()->where('tenant_id', $this->tenant->id)->firstOrFail();
    Appointment::factory()->create(['tenant_id' => $this->tenant->id, 'service_request_id' => $request->id, 'professional_id' => Professional::factory()->create(['tenant_id' => $this->tenant->id])->id]);

    $this->actingAs($this->root)->get('/rootadmin/crescimento')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('overview.active_tenants', 2)
            ->where('tenants.data', function ($rows) use ($other) {
                $clima = collect($rows)->firstWhere('name', 'Clima Total');
                $outra = collect($rows)->firstWhere('id', $other->id);

                return $clima['requests'] === 6 && $clima['completed'] === 3 && $clima['appointments'] === 1 && $clima['conversion'] === 50
                    && $outra['requests'] === 2 && $outra['conversion'] === null && $clima['seo']['indexable'] === false;
            }));
});

test('Crescimento e SEO leaves homologation companies out of the overview', function () {
    $homolog = Tenant::factory()->create(['is_homolog' => true]);
    requestsFor($homolog, 3);

    $this->actingAs($this->root)->get('/rootadmin/crescimento')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('overview.active_tenants', 0)->where('overview.tenants', 1));
});

test('a support record is opened with validation, history and audit', function () {
    $this->actingAs($this->root)->post('/rootadmin/suporte', ['subject' => 'Oi', 'description' => 'curto', 'priority' => 'x'])
        ->assertSessionHasErrors(['subject' => 'O assunto precisa de ao menos 5 caracteres.', 'description' => 'A descrição precisa de ao menos 10 caracteres.', 'priority' => 'Escolha uma prioridade válida.']);

    $this->actingAs($this->root)->post('/rootadmin/suporte', ['tenant_id' => $this->tenant->id, 'subject' => 'Domínio não verifica', 'description' => 'O registro TXT não aparece para a empresa.', 'priority' => 'high'])
        ->assertRedirect();

    $ticket = SupportTicket::query()->sole();
    expect($ticket->status)->toBe(SupportTicketStatus::Open)
        ->and($ticket->opened_by)->toBe($this->root->id)
        ->and($ticket->events()->sole()->type)->toBe('opened')
        ->and(AuditLog::query()->where('action', 'support.ticket_opened')->count())->toBe(1);
});

test('a follow-up records comment, status and priority changes, and resolving sets the closing date', function () {
    $this->actingAs($this->root)->post('/rootadmin/suporte', ['subject' => 'Falha no envio', 'description' => 'Os e-mails de confirmação não chegam.', 'priority' => 'normal']);
    $ticket = SupportTicket::query()->sole();

    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/acompanhamentos", [])
        ->assertSessionHasErrors(['note' => 'Escreva o acompanhamento ou altere a situação, a prioridade, o responsável ou o prazo.']);

    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/acompanhamentos", ['note' => 'SMTP corrigido.', 'status' => 'resolved', 'priority' => 'urgent'])->assertRedirect();

    $ticket->refresh();
    expect($ticket->status)->toBe(SupportTicketStatus::Resolved)
        ->and($ticket->closed_at)->not->toBeNull()
        ->and($ticket->events()->pluck('type')->sort()->values()->all())->toBe(['comment', 'opened', 'priority', 'status'])
        ->and(AuditLog::query()->where('action', 'support.ticket_updated')->sole()->metadata['status'])->toBe(['de' => 'open', 'para' => 'resolved']);
});

test('the support list shows open records by default and the companies with problems', function () {
    $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();
    $open = SupportTicket::query()->forceCreate(['subject' => 'Em aberto', 'description' => 'Descrição longa.', 'status' => 'open', 'priority' => 'normal', 'last_activity_at' => now()]);
    SupportTicket::query()->forceCreate(['subject' => 'Já resolvido', 'description' => 'Descrição longa.', 'status' => 'resolved', 'priority' => 'normal', 'last_activity_at' => now()]);

    $this->actingAs($this->root)->get('/rootadmin/suporte')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('tickets.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$open->id])
            ->where('signals.suspended.0.tenant_id', $this->tenant->id));
});

test('company users cannot reach the new RootAdmin screens', function (string $method, string $path) {
    $ticket = SupportTicket::query()->forceCreate(['subject' => 'Interno', 'description' => 'Descrição longa.', 'status' => 'open', 'priority' => 'normal']);

    $this->actingAs($this->owner)->{$method}(str_replace('{ticket}', (string) $ticket->id, $path), ['subject' => 'Tentativa', 'description' => 'Tentativa indevida aqui.', 'priority' => 'low', 'note' => 'x'])
        ->assertForbidden();

    expect(SupportTicket::query()->count())->toBe(1)
        ->and($ticket->events()->count())->toBe(0);
})->with([
    'configurações' => ['get', '/rootadmin/configuracoes'],
    'crescimento' => ['get', '/rootadmin/crescimento'],
    'suporte' => ['get', '/rootadmin/suporte'],
    'abrir chamado' => ['post', '/rootadmin/suporte'],
    'ver chamado' => ['get', '/rootadmin/suporte/{ticket}'],
    'acompanhar' => ['post', '/rootadmin/suporte/{ticket}/acompanhamentos'],
]);
