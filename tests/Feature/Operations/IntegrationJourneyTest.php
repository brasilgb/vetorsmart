<?php

use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Segment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantNotification;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Portal\PortalReferral;
use App\Services\Websites\WebsiteService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;

/**
 * INTEGRATION-01: the whole journey, from a request sent from the Regional Portal to the closing of the service in the
 * panel, with notices, history and Meu Crescimento.
 */
beforeEach(function () {
    $segment = Segment::factory()->create(['name' => 'Climatização', 'slug' => 'climatizacao', 'active' => true]);
    $this->tenant = Tenant::factory()->create(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment_id' => $segment->id]);
    TenantSetting::factory()->create(['tenant_id' => $this->tenant->id, 'business_name' => 'Clima Total', 'city' => 'Campinas', 'state' => 'SP']);
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id, 'role' => 'operator']);

    $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação de split', 'duration_minutes' => 60]);
    $this->professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->professional->services()->attach($this->service->id, ['tenant_id' => $this->tenant->id]);
    foreach ([1, 2] as $weekday) {
        ProfessionalAvailability::create(['tenant_id' => $this->tenant->id, 'professional_id' => $this->professional->id, 'weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '12:00:00']);
    }
    $this->monday = CarbonImmutable::now($this->tenant->timezone)->next(CarbonImmutable::MONDAY);

    $websites = app(WebsiteService::class);
    $websites->saveDraft($this->tenant, ['service_ids' => [$this->service->id]], $this->owner);
    $websites->publish($this->tenant, $this->owner);
    $websites->setPortalListing($this->tenant, true, $this->owner);
});

/**
 * A visitor request from the portal, with a send token: the visitor arrives through the signed link of a portal card
 * (the site records the arrival on the server) and sends the form.
 */
function journeyRequest(string $token, int $serviceId): void
{
    $tenant = Tenant::query()->where('slug', 'clima-total')->sole();
    test()->get('/climatizacao/clima-total/solicitar?'.app(PortalReferral::class)->query($tenant->id))->assertOk();

    test()->post('/climatizacao/clima-total/solicitar', [
        'name' => 'Maria Souza', 'phone' => '19988887777', 'description' => 'Instalar um split.', 'consent' => '1', 'municipality' => 'Campinas - SP',
        'service_id' => $serviceId, 'envio' => $token,
    ])->assertSessionHas('site_request_sent', true);
}

/**
 * @return array<string, mixed>
 */
function portalFigures(User $owner): array
{
    $figures = [];
    test()->actingAs($owner)->get('/meu-crescimento')->assertInertia(function (AssertableInertia $page) use (&$figures): void {
        $figures = ['current' => $page->toArray()['props']['current'], 'portal' => collect($page->toArray()['props']['origins'])->firstWhere('origin', 'portal')];
    });

    return $figures;
}

test('a request from the portal goes through notice, triage, scheduling, rescheduling, execution and completion, with history and indicators', function () {
    journeyRequest(str_repeat('a', 40), $this->service->id);
    $request = ServiceRequest::query()->sole();

    // Received and noticed: the team (operator included) sees it in the panel notices.
    expect($request->only(['origin', 'status', 'service_id']))->toBe(['origin' => RequestOrigin::Portal, 'status' => RequestStatus::Triaged, 'service_id' => $this->service->id])
        ->and(TenantNotification::query()->where('tenant_id', $this->tenant->id)->pluck('user_id')->sort()->values()->all())->toBe(collect([$this->owner->id, $this->operator->id])->sort()->values()->all());

    // The operator works it from the panel: schedule, reschedule in the agenda, start and complete.
    $this->actingAs($this->operator)->get('/solicitacoes?origem=portal')->assertInertia(fn ($page) => $page->where('requests.data.0.id', $request->id));
    $this->actingAs($this->operator)->post(route('tenant.requests.schedule', $request), ['professional_id' => $this->professional->id, 'starts_at' => $this->monday->setTime(9, 0)->format('Y-m-d H:i')])->assertSessionHasNoErrors();
    $appointment = $request->appointments()->sole();
    $this->actingAs($this->operator)->patch(route('tenant.agenda.reschedule', $appointment), ['starts_at' => $this->monday->addDay()->setTime(10, 0)->format('Y-m-d H:i')])->assertSessionHasNoErrors();
    $this->actingAs($this->operator)->get('/solicitacoes/'.$request->id)->assertInertia(fn ($page) => $page->where('appointments.0.date', $this->monday->addDay()->format('Y-m-d')));
    $this->actingAs($this->operator)->post(route('tenant.requests.start', $request))->assertSessionHasNoErrors();
    $this->actingAs($this->operator)->post(route('tenant.requests.complete', $request))->assertSessionHasNoErrors();

    $request->refresh();
    expect($request->status)->toBe(RequestStatus::Completed)
        ->and($request->origin)->toBe(RequestOrigin::Portal)
        ->and($request->events()->orderBy('id')->pluck('to_status')->filter()->values()->all())->toBe(['requested', 'triaged', 'scheduled', 'in_progress', 'completed']);

    // Meu Crescimento (owner only): the same request received, scheduled and completed, never cancelled.
    $figures = portalFigures($this->owner);
    expect($figures['portal'])->toMatchArray(['received' => 1, 'scheduled' => 1, 'completed' => 1, 'cancelled' => 0])
        ->and($figures['current'])->toMatchArray(['requests' => 1, 'completed' => 1, 'cancelled' => 0]);
    $this->actingAs($this->operator)->get('/meu-crescimento')->assertForbidden();
});

test('a cancelled request is counted as cancelled, never as completed', function () {
    journeyRequest(str_repeat('b', 40), $this->service->id);
    $request = ServiceRequest::query()->sole();
    $this->actingAs($this->owner)->post(route('tenant.requests.schedule', $request), ['professional_id' => $this->professional->id, 'starts_at' => $this->monday->setTime(9, 0)->format('Y-m-d H:i')]);
    $this->actingAs($this->owner)->post(route('tenant.requests.cancel', $request), ['reason' => 'Cliente desistiu.'])->assertSessionHasNoErrors();

    $figures = portalFigures($this->owner);
    expect($figures['portal'])->toMatchArray(['received' => 1, 'scheduled' => 1, 'completed' => 0, 'cancelled' => 1])
        ->and($figures['current'])->toMatchArray(['completed' => 0, 'cancelled' => 1]);
});

test('a failing notice never turns a saved request into an error, nor allows a duplicate', function () {
    Log::spy();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('fila indisponível'));
    $token = str_repeat('c', 40);

    journeyRequest($token, $this->service->id);
    journeyRequest($token, $this->service->id);

    expect(ServiceRequest::query()->count())->toBe(1);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'Operational notice not delivered.' && ! str_contains(json_encode($context), 'Maria'));
});

test('a second send while the first is still registering never confirms and creates nothing', function () {
    config(['platform.site_request_lock_wait' => 0]);
    $token = str_repeat('d', 40);
    $lock = Cache::lock('site-request:'.$this->tenant->id.':'.hash('sha256', $token).':lock', 15);
    $lock->get();

    $this->post('/climatizacao/clima-total/solicitar', ['name' => 'Maria Souza', 'phone' => '19988887777', 'description' => 'Instalar um split.', 'consent' => '1', 'municipality' => 'Campinas - SP', 'envio' => $token])
        ->assertSessionHasErrors(['envio' => 'Sua solicitação ainda está sendo registrada. Aguarde alguns segundos e tente novamente.'])
        ->assertSessionMissing('site_request_sent');
    expect(ServiceRequest::query()->count())->toBe(0);

    $lock->release();
    journeyRequest($token, $this->service->id);
    expect(ServiceRequest::query()->count())->toBe(1);
});
