<?php

use App\Enums\SupportTicketStatus;
use App\Models\SupportTicket;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\OperationalMail;
use App\Services\OperationalNotifier;
use App\Services\Support\SupportTicketService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/**
 * PANELS-08: support notifications through the queue, next action on replies, Google review link, company tickets in
 * the RootAdmin company detail.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Clima Total']);
    TenantSetting::factory()->create(['tenant_id' => $this->tenant->id, 'business_name' => 'Clima Total', 'settings' => ['currency' => 'BRL', 'date_format' => 'd/m/Y']]);
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id]);
    $this->root = User::factory()->rootAdmin()->create();
});

function openedTicket(Tenant $tenant, User $owner): SupportTicket
{
    test()->actingAs($owner)->post('/suporte', ['subject' => 'Agenda não abre', 'category' => 'technical', 'description' => 'A agenda fica carregando sem parar.']);

    return SupportTicket::query()->where('tenant_id', $tenant->id)->latest('id')->firstOrFail();
}

test('a public answer of the team notifies owners and admins through the queue, without the answer text', function () {
    Notification::fake();
    $ticket = openedTicket($this->tenant, $this->owner);

    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/acompanhamentos", ['note' => 'Senha provisória: 1234', 'await_company' => true])->assertRedirect();

    Notification::assertSentTo($this->owner, OperationalMail::class, function (OperationalMail $mail) use ($ticket): bool {
        return $mail instanceof ShouldQueue
            && str_contains($mail->subjectLine, "#{$ticket->id}")
            && ! str_contains(implode(' ', $mail->lines), 'Senha provisória');
    });
    Notification::assertNotSentTo($this->operator, OperationalMail::class);
    expect(TenantNotification::query()->where('user_id', $this->owner->id)->where('title', "Resposta da equipe VetorSmart no chamado #{$ticket->id}")->count())->toBe(1);
});

test('internal notes never notify the company', function () {
    Notification::fake();
    $ticket = openedTicket($this->tenant, $this->owner);

    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/acompanhamentos", ['note' => 'Cliente insistente.', 'internal' => true])->assertRedirect();

    Notification::assertNothingSentTo($this->owner);
    expect(TenantNotification::query()->count())->toBe(0);
});

test('the same answer is notified once', function () {
    Notification::fake();
    $ticket = openedTicket($this->tenant, $this->owner);
    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/acompanhamentos", ['note' => 'Verificando.']);
    $eventId = $ticket->events()->where('type', 'comment')->value('id');

    app(OperationalNotifier::class)->supportAnswered($ticket->fresh(), $eventId, $this->root);

    Notification::assertSentToTimes($this->owner, OperationalMail::class, 1);
});

test('a public answer can hand the next action to the company, and the company answer gives it back to the team with a notice', function () {
    Notification::fake();
    $ticket = openedTicket($this->tenant, $this->owner);
    $ticket->forceFill(['assigned_to' => $this->root->id])->save();

    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/acompanhamentos", ['note' => 'Pode enviar um print?', 'await_company' => true]);
    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Waiting)
        ->and($ticket->fresh()->status->nextActionBy())->toBe('company');

    $this->actingAs($this->owner)->post("/suporte/{$ticket->id}/respostas", ['note' => 'Segue o print.'])->assertRedirect();

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Open)
        ->and($ticket->fresh()->status->nextActionBy())->toBe('team');
    Notification::assertSentTo($this->root, OperationalMail::class, fn (OperationalMail $mail): bool => str_contains($mail->subjectLine, "#{$ticket->id}"));
});

test('without await_company the answer keeps the request with the team, and a company answer on it sends no notice', function () {
    Notification::fake();
    $ticket = openedTicket($this->tenant, $this->owner);

    $this->actingAs($this->root)->post("/rootadmin/suporte/{$ticket->id}/acompanhamentos", ['note' => 'Estamos verificando.', 'await_company' => false]);
    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Open);

    $this->actingAs($this->owner)->post("/suporte/{$ticket->id}/respostas", ['note' => 'Obrigado.']);
    Notification::assertNotSentTo($this->root, OperationalMail::class);
});

test('a company answer with nobody assigned warns the active platform administrators', function () {
    Notification::fake();
    $ticket = openedTicket($this->tenant, $this->owner);
    $otherRoot = User::factory()->rootAdmin()->create();
    app(SupportTicketService::class)->platformUpdate($ticket, ['status' => SupportTicketStatus::Waiting], $this->root);

    $this->actingAs($this->owner)->post("/suporte/{$ticket->id}/respostas", ['note' => 'Segue a informação.']);

    Notification::assertSentTo([$this->root, $otherRoot], OperationalMail::class);
});

test('a failing notice never blocks the support request', function () {
    $ticket = openedTicket($this->tenant, $this->owner);
    app(SupportTicketService::class)->platformUpdate($ticket, ['status' => SupportTicketStatus::Waiting], $this->root);
    Notification::shouldReceive('send')->andThrow(new RuntimeException('SMTP fora do ar'));

    $this->actingAs($this->owner)->post("/suporte/{$ticket->id}/respostas", ['note' => 'Segue a informação.'])->assertRedirect();

    expect($ticket->fresh()->status)->toBe(SupportTicketStatus::Open)
        ->and($ticket->events()->where('type', 'comment')->count())->toBe(1);
});

test('the Google review link accepts only Google addresses and keeps the other preferences', function () {
    $this->actingAs($this->owner)->patch('/configuracoes', ['google_review_url' => 'https://avalie.example.com/x'])
        ->assertSessionHasErrors(['google_review_url' => 'Use o link de avaliação gerado pelo Google (google.com, g.page ou goo.gl).']);

    $this->actingAs($this->owner)->patch('/configuracoes', ['google_review_url' => 'https://g.page/r/ABC123/review'])->assertSessionHasNoErrors();

    $settings = TenantSetting::query()->forTenant($this->tenant)->sole()->settings;
    expect($settings['google_review_url'])->toBe('https://g.page/r/ABC123/review')
        ->and($settings['currency'])->toBe('BRL')
        ->and($settings['date_format'])->toBe('d/m/Y');
});

test('the RootAdmin company detail lists only the tickets of that company', function () {
    $mine = openedTicket($this->tenant, $this->owner);
    $other = Tenant::factory()->create();
    $otherOwner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $other->id, 'user_id' => $otherOwner->id]);
    TenantSetting::factory()->create(['tenant_id' => $other->id]);
    openedTicket($other, $otherOwner);

    $this->actingAs($this->root)->get("/rootadmin/tenants/{$this->tenant->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page->where('support.open', 1)->where('support.recent', fn ($rows) => collect($rows)->pluck('id')->all() === [$mine->id]));
});
