<?php

use App\Enums\AppointmentStatus;
use App\Enums\NotificationCategory;
use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Enums\TenantStatus;
use App\Models\AssistantConversation;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\OperationalMail;
use App\Services\AppointmentScheduler;
use App\Services\Assistant\AssistantConversations;
use App\Services\OperationalNotifier;
use App\Services\ServiceRequestService;
use App\Services\TenantStatusService;
use Carbon\CarbonImmutable;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    Notification::fake();

    $this->tenant = Tenant::factory()->create(['name' => 'Clima Total']);
    TenantSetting::factory()->create(['tenant_id' => $this->tenant->id, 'business_name' => 'Clima Total Ltda']);

    $this->owner = User::factory()->create();
    $this->operator = User::factory()->create();
    $this->ownerMembership = TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id]);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Maria Souza', 'email' => 'maria@example.com']);
    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação', 'duration_minutes' => 60]);
    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->professional->services()->attach($this->service->id, ['tenant_id' => $this->tenant->id]);
    ProfessionalAvailability::create(['tenant_id' => $this->tenant->id, 'professional_id' => $this->professional->id, 'weekday' => 1, 'start_time' => '08:00:00', 'end_time' => '12:00:00']);
    $this->monday = CarbonImmutable::now($this->tenant->timezone)->next(CarbonImmutable::MONDAY)->setTime(9, 0);
});

/**
 * A mail notification sent to the customer's address.
 */
function customerMails(string $email): Closure
{
    return fn (OperationalMail $mail, array $channels, object $notifiable): bool => $notifiable instanceof AnonymousNotifiable
        && array_key_exists($email, (array) $notifiable->routeNotificationFor('mail'));
}

function notifiedTriagedRequest(Tenant $tenant, Customer $customer, Service $service): ServiceRequest
{
    return ServiceRequest::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'service_id' => $service->id, 'status' => RequestStatus::Triaged]);
}

test('a request from the public site notifies every member in the panel and by e-mail, and confirms to the customer', function () {
    $request = app(ServiceRequestService::class)->create($this->tenant, ['customer_id' => $this->customer->id, 'description' => 'Ar não gela'], null, RequestOrigin::PublicSite);

    $notices = TenantNotification::query()->forTenant($this->tenant)->get();

    expect($notices)->toHaveCount(2)
        ->and($notices->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->owner->id, $this->operator->id])->sort()->values()->all())
        ->and($notices->first()->category)->toBe(NotificationCategory::RequestReceived)
        ->and($notices->first()->title)->toBe('Nova solicitação de Maria Souza')
        ->and($notices->first()->url)->toBe("/solicitacoes/{$request->id}");

    Notification::assertSentTo($this->owner, OperationalMail::class);
    Notification::assertSentTo($this->operator, OperationalMail::class);
    Notification::assertSentOnDemand(OperationalMail::class, customerMails('maria@example.com'));
});

test('the person who caused the event is not notified about it', function () {
    app(ServiceRequestService::class)->create($this->tenant, ['customer_id' => $this->customer->id, 'description' => 'Revisão'], $this->owner);

    expect(TenantNotification::query()->where('user_id', $this->owner->id)->exists())->toBeFalse()
        ->and(TenantNotification::query()->where('user_id', $this->operator->id)->exists())->toBeTrue();
    Notification::assertNotSentTo($this->owner, OperationalMail::class);
});

test('nothing is notified when the transaction that produced the event is rolled back', function () {
    try {
        DB::transaction(function () {
            app(ServiceRequestService::class)->create($this->tenant, ['customer_id' => $this->customer->id, 'description' => 'Revisão'], null, RequestOrigin::Ai);

            throw new RuntimeException('falha depois da solicitação');
        });
    } catch (RuntimeException) {
        // The operation failed after the request was written.
    }

    expect(ServiceRequest::query()->count())->toBe(0)
        ->and(TenantNotification::query()->count())->toBe(0)
        ->and(DB::table('notification_deliveries')->count())->toBe(0);
    Notification::assertNothingSent();
});

test('a repeated event notifies only once', function () {
    $request = ServiceRequest::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);

    app(OperationalNotifier::class)->requestReceived($request, null);
    app(OperationalNotifier::class)->requestReceived($request, null);

    expect(TenantNotification::query()->count())->toBe(2);
    Notification::assertSentToTimes($this->owner, OperationalMail::class, 1);
    Notification::assertSentOnDemandTimes(OperationalMail::class, 1);
});

test('booking, moving and cancelling an appointment notify the team and the customer once each', function () {
    $scheduler = app(AppointmentScheduler::class);
    $request = notifiedTriagedRequest($this->tenant, $this->customer, $this->service);

    $appointment = $scheduler->schedule($request, $this->professional, $this->monday->utc(), null);
    $scheduler->reschedule($appointment, $this->monday->addHour()->utc(), $this->owner);
    $scheduler->cancel($appointment->fresh(), 'Cliente pediu', $this->owner);

    $categories = TenantNotification::query()->where('user_id', $this->operator->id)->pluck('category')->map->value->all();

    expect($categories)->toBe(['appointment_confirmed', 'appointment_confirmed', 'appointment_cancelled'])
        ->and(TenantNotification::query()->where('user_id', $this->owner->id)->pluck('category')->map->value->all())->toBe(['appointment_confirmed']);
    Notification::assertSentOnDemandTimes(OperationalMail::class, 3);
});

test('cancelling a request cancels its appointment and tells the customer', function () {
    $request = notifiedTriagedRequest($this->tenant, $this->customer, $this->service);
    $appointment = app(AppointmentScheduler::class)->schedule($request, $this->professional, $this->monday->utc(), $this->owner);

    $this->actingAs($this->owner)->post(route('tenant.requests.cancel', $request), ['reason' => 'Desistiu'])->assertRedirect();

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(TenantNotification::query()->where('category', NotificationCategory::AppointmentCancelled->value)->where('user_id', $this->operator->id)->exists())->toBeTrue();
    Notification::assertSentOnDemand(OperationalMail::class, fn (OperationalMail $mail) => $mail->subjectLine === 'Agendamento cancelado · Clima Total Ltda');
});

test('the customer is told when the service is concluded, the team is not', function () {
    $request = ServiceRequest::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'service_id' => $this->service->id, 'status' => RequestStatus::InProgress]);

    app(ServiceRequestService::class)->complete($request, $this->owner);

    expect(TenantNotification::query()->count())->toBe(0);
    Notification::assertSentOnDemand(OperationalMail::class, fn (OperationalMail $mail) => $mail->subjectLine === 'Atendimento concluído · Clima Total Ltda');
});

test('a conversation handed over to the team is notified once while it keeps waiting', function () {
    $conversation = AssistantConversation::query()->forceCreate(['tenant_id' => $this->tenant->id, 'session_hash' => str_repeat('a', 64), 'status' => 'active', 'state' => 'greeting', 'last_activity_at' => now()]);
    $conversations = app(AssistantConversations::class);

    $conversations->handoff($conversation, 'Visitante pediu um atendente');
    $conversations->handoff($conversation->fresh(), 'Visitante insistiu');

    expect(TenantNotification::query()->where('category', NotificationCategory::AwaitingAttendance->value)->count())->toBe(2)
        ->and(TenantNotification::query()->where('user_id', $this->operator->id)->value('url'))->toBe("/atendimento-ia?conversation={$conversation->id}");
});

test('an optional e-mail turned off keeps the panel notice, and the customer switch stops customer e-mails', function () {
    $this->ownerMembership->forceFill(['notification_preferences' => ['request_received' => false]])->save();
    TenantSetting::query()->forTenant($this->tenant)->update(['settings' => json_encode([OperationalNotifier::CUSTOMER_EMAILS_SETTING => false])]);

    app(ServiceRequestService::class)->create($this->tenant, ['customer_id' => $this->customer->id, 'description' => 'Revisão'], null, RequestOrigin::PublicSite);

    expect(TenantNotification::query()->where('user_id', $this->owner->id)->exists())->toBeTrue();
    Notification::assertNotSentTo($this->owner, OperationalMail::class);
    Notification::assertSentTo($this->operator, OperationalMail::class);
    Notification::assertSentOnDemandTimes(OperationalMail::class, 0);
});

test('a member who has not confirmed the e-mail gets the panel notice but no e-mail', function () {
    $this->operator->forceFill(['email_verified_at' => null])->save();

    app(ServiceRequestService::class)->create($this->tenant, ['customer_id' => $this->customer->id, 'description' => 'Revisão'], null, RequestOrigin::Ai);

    expect(TenantNotification::query()->where('user_id', $this->operator->id)->exists())->toBeTrue();
    Notification::assertNotSentTo($this->operator, OperationalMail::class);
});

test('a customer without an e-mail address receives nothing', function () {
    $this->customer->update(['email' => null]);

    app(ServiceRequestService::class)->create($this->tenant, ['customer_id' => $this->customer->id, 'description' => 'Revisão'], null, RequestOrigin::Ai);

    Notification::assertSentOnDemandTimes(OperationalMail::class, 0);
});

test('a suspended company has its owners told by e-mail even when they turned optional e-mails off', function () {
    $this->ownerMembership->forceFill(['notification_preferences' => array_fill_keys(array_map(fn ($category) => $category->value, NotificationCategory::optional()), false)])->save();
    $root = User::factory()->rootAdmin()->create();

    app(TenantStatusService::class)->change($this->tenant, TenantStatus::Suspended, $root);

    Notification::assertSentTo($this->owner, OperationalMail::class, fn (OperationalMail $mail) => str_starts_with($mail->subjectLine, 'Acesso da empresa suspenso'));
    Notification::assertNotSentTo($this->operator, OperationalMail::class);
});

test('members of another company are never notified', function () {
    $other = Tenant::factory()->create();
    $stranger = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $other->id, 'user_id' => $stranger->id]);

    app(ServiceRequestService::class)->create($this->tenant, ['customer_id' => $this->customer->id, 'description' => 'Revisão'], null, RequestOrigin::Ai);

    expect(TenantNotification::query()->where('user_id', $stranger->id)->exists())->toBeFalse();
    Notification::assertNotSentTo($stranger, OperationalMail::class);
});

test('inactive members and suspended accounts are not notified', function () {
    $this->operator->forceFill(['status' => 'suspended'])->save();

    app(ServiceRequestService::class)->create($this->tenant, ['customer_id' => $this->customer->id, 'description' => 'Revisão'], null, RequestOrigin::Ai);

    expect(TenantNotification::query()->where('user_id', $this->operator->id)->exists())->toBeFalse();
});

test('the panel link of an e-mail points to the platform address, not to the host where the event happened', function () {
    config(['platform.public_url' => 'https://vetorsmart.com.br']);

    $request = app(ServiceRequestService::class)->create($this->tenant, ['customer_id' => $this->customer->id, 'description' => 'Revisão'], null, RequestOrigin::PublicSite);

    Notification::assertSentTo($this->owner, OperationalMail::class, fn (OperationalMail $mail) => $mail->actionUrl === "https://vetorsmart.com.br/solicitacoes/{$request->id}");
});

test('customer e-mails escape what the customer typed', function () {
    $this->customer->update(['name' => '<script>alert(1)</script> Maria']);

    app(ServiceRequestService::class)->create($this->tenant, ['customer_id' => $this->customer->id, 'description' => '<b>urgente</b>'], null, RequestOrigin::PublicSite);

    Notification::assertSentOnDemand(OperationalMail::class, function (OperationalMail $mail) {
        $html = (string) $mail->toMail(new AnonymousNotifiable)->render();

        return ! str_contains($html, '<script>alert(1)</script>') && ! str_contains($html, '<b>urgente</b>');
    });
});
