<?php

use App\Enums\NotificationCategory;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\OperationalNotifier;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    TenantSetting::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->owner = User::factory()->create();
    $this->operator = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->operatorMembership = TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id]);
});

function noticeFor(Tenant $tenant, User $user, array $attributes = []): TenantNotification
{
    return TenantNotification::query()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'category' => NotificationCategory::RequestReceived,
        'title' => 'Nova solicitação',
        'url' => '/solicitacoes/1',
        ...$attributes,
    ]);
}

test('the list shows only the notices of the signed-in person in the active company, newest first', function () {
    $older = noticeFor($this->tenant, $this->operator, ['title' => 'Antiga']);
    $newer = noticeFor($this->tenant, $this->operator, ['title' => 'Recente']);
    noticeFor($this->tenant, $this->owner, ['title' => 'Do proprietário']);
    $other = Tenant::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $other->id, 'user_id' => $this->operator->id]);
    noticeFor($other, $this->operator, ['title' => 'De outra empresa']);

    $this->actingAs($this->operator)
        ->withSession(['current_tenant_id' => $this->tenant->id])
        ->get('/notificacoes')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('tenant/notifications/index')
            ->has('notifications.data', 2)
            ->where('notifications.data.0.id', $newer->id)
            ->where('notifications.data.1.id', $older->id)
            ->where('unreadNotifications', 2)
            ->where('customerEmails', null));
});

test('the list is paginated', function () {
    foreach (range(1, 25) as $index) {
        noticeFor($this->tenant, $this->operator, ['title' => "Aviso {$index}"]);
    }

    $this->actingAs($this->operator)->get('/notificacoes?page=2')
        ->assertInertia(fn (AssertableInertia $page) => $page->has('notifications.data', 5));
});

test('opening a notice marks it as read and goes to the related record', function () {
    $notice = noticeFor($this->tenant, $this->operator, ['url' => '/agenda?view=day&date=2026-11-20']);

    $this->actingAs($this->operator)->post("/notificacoes/{$notice->id}/abrir")->assertRedirect('/agenda?view=day&date=2026-11-20');

    expect($notice->fresh()->read_at)->not->toBeNull();
});

test('a notice without a panel path opens the list instead of leaving the site', function (?string $url) {
    $notice = noticeFor($this->tenant, $this->operator, ['url' => $url]);

    $this->actingAs($this->operator)->post("/notificacoes/{$notice->id}/abrir")->assertRedirect(route('tenant.notifications.index'));
})->with([null, 'https://externo.example', '//externo.example/x']);

test('the notice of another person or of another company is answered as missing', function () {
    $ownerNotice = noticeFor($this->tenant, $this->owner);
    $other = Tenant::factory()->create();
    $foreign = noticeFor($other, User::factory()->create());

    $this->actingAs($this->operator)->post("/notificacoes/{$ownerNotice->id}/abrir")->assertNotFound();
    $this->actingAs($this->operator)->post("/notificacoes/{$foreign->id}/lida")->assertNotFound();

    expect($ownerNotice->fresh()->read_at)->toBeNull()
        ->and($foreign->fresh()->read_at)->toBeNull();
});

test('marking everything as read touches only the person own notices in the active company', function () {
    $mine = noticeFor($this->tenant, $this->operator);
    $theirs = noticeFor($this->tenant, $this->owner);

    $this->actingAs($this->operator)->post('/notificacoes/lidas')->assertRedirect();

    expect($mine->fresh()->read_at)->not->toBeNull()
        ->and($theirs->fresh()->read_at)->toBeNull();
});

test('the header count follows the unread notices', function () {
    noticeFor($this->tenant, $this->operator);
    noticeFor($this->tenant, $this->operator)->forceFill(['read_at' => now()])->save();

    $this->actingAs($this->operator)->get('/clientes')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('unreadNotifications', 1));
});

test('a person saves their own e-mail preferences', function () {
    $this->actingAs($this->operator)->patch('/notificacoes/preferencias', [
        'email' => ['request_received' => false, 'appointment_cancelled' => true],
    ])->assertRedirect(route('tenant.notifications.index'));

    expect($this->operatorMembership->fresh()->notification_preferences)->toBe(['request_received' => false, 'appointment_cancelled' => true]);
});

test('administrative notices have no switch', function () {
    $this->actingAs($this->operator)
        ->patch('/notificacoes/preferencias', ['email' => ['admin_notice' => false]])
        ->assertSessionHasErrors(['email' => 'Preferência de e-mail inválida.']);

    expect($this->operatorMembership->fresh()->notification_preferences)->toBeNull();
});

test('an operator cannot change the company switch for customer e-mails', function () {
    $this->actingAs($this->operator)->patch('/notificacoes/preferencias', ['customer_emails' => false])->assertForbidden();

    expect(TenantSetting::query()->forTenant($this->tenant)->first()->settings[OperationalNotifier::CUSTOMER_EMAILS_SETTING] ?? true)->toBeTrue();
});

test('an owner turns the customer e-mails off for the company', function () {
    $this->actingAs($this->owner)->patch('/notificacoes/preferencias', ['customer_emails' => false])->assertRedirect();

    expect(TenantSetting::query()->forTenant($this->tenant)->first()->settings[OperationalNotifier::CUSTOMER_EMAILS_SETTING])->toBeFalse();

    $this->actingAs($this->owner)->get('/notificacoes')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('customerEmails', false));
});

test('a visitor is sent to the sign-in page', function () {
    $this->get('/notificacoes')->assertRedirect(route('login'));
});
