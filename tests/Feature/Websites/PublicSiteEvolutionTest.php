<?php

use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Models\Appointment;
use App\Models\AssistantSetting;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Segment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\TenantWebsite;
use App\Models\User;
use App\Services\Websites\TrustedHosts;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * WEBSITES-10: logo and main image isolated per company, segment theme, service region, request form of the public
 * site and its integration with the triage queue, SEO of the new pages.
 */
beforeEach(function () {
    Storage::fake('local');

    $this->segment = Segment::factory()->create(['name' => 'Climatização', 'slug' => 'climatizacao', 'active' => true]);
    $this->tenant = Tenant::factory()->create(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment_id' => $this->segment->id]);
    TenantSetting::factory()->create(['tenant_id' => $this->tenant->id, 'business_name' => 'Clima Total Ltda', 'whatsapp' => '(19) 99999-0000']);
    $this->owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    $this->service = Service::factory()->fixed('250.00')->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação de split']);

    $this->actingAs($this->owner);
});

/**
 * Publish the signed-in company's site with the given services.
 *
 * @param  list<int>  $serviceIds
 */
function publishSiteWith(array $serviceIds, bool $showPrices = true): void
{
    test()->patch('/meu-site/rascunho', ['section' => 'services', 'service_ids' => $serviceIds, 'show_prices' => $showPrices])->assertRedirect('/meu-site');
    test()->post('/meu-site/publicar')->assertRedirect('/meu-site');
}

function draftImage(Tenant $tenant, string $kind): ?string
{
    return TenantWebsite::query()->forTenant($tenant)->value('draft_content')[$kind]['file'] ?? null;
}

test('an uploaded logo is re-encoded into the company folder and reaches visitors only after publication', function () {
    $this->post('/meu-site/imagens/logo', ['image' => UploadedFile::fake()->image('logo.png', 1200, 400)])->assertRedirect('/meu-site')->assertSessionHasNoErrors();

    $file = draftImage($this->tenant, 'logo');
    expect($file)->toMatch('/^logo-[a-z0-9]{40}\.webp$/');
    Storage::disk('local')->assertExists("websites/{$this->tenant->id}/{$file}");
    expect(getimagesizefromstring(Storage::disk('local')->get("websites/{$this->tenant->id}/{$file}"))[0])->toBe(600);

    $this->get("/meu-site/previa/midia/{$file}")->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get('/meu-site/previa')->assertSee("/meu-site/previa/midia/{$file}", false);

    publishSiteWith([]);
    $this->post('/meu-site/imagens/logo', ['image' => UploadedFile::fake()->image('novo.png', 300, 300)]);
    $draftOnly = draftImage($this->tenant, 'logo');

    $this->get("/climatizacao/clima-total/midia/{$file}")->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get("/climatizacao/clima-total/midia/{$draftOnly}")->assertNotFound();
    $this->get('/climatizacao/clima-total')->assertSee("midia/{$file}", false)->assertDontSee($draftOnly, false);
});

test('images never cross companies, neither on the public address nor in the preview', function () {
    $this->post('/meu-site/imagens/hero', ['image' => UploadedFile::fake()->image('foto.jpg', 1000, 600)]);
    $file = draftImage($this->tenant, 'hero');
    publishSiteWith([]);

    $other = Tenant::factory()->create(['slug' => 'frio-bom', 'segment_id' => $this->segment->id]);
    TenantSetting::factory()->create(['tenant_id' => $other->id]);
    $otherOwner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $other->id, 'user_id' => $otherOwner->id]);
    $this->actingAs($otherOwner);
    publishSiteWith([]);

    $this->get("/climatizacao/frio-bom/midia/{$file}")->assertNotFound();
    $this->get("/meu-site/previa/midia/{$file}")->assertNotFound();
    $this->get("/climatizacao/clima-total/midia/{$file}")->assertOk();
});

test('only real images of the allowed types and size are accepted, and only by who manages the site', function () {
    $this->post('/meu-site/imagens/logo', ['image' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
        ->assertSessionHasErrors(['image' => 'Envie uma imagem JPG, PNG ou WebP.']);
    $this->post('/meu-site/imagens/logo', ['image' => UploadedFile::fake()->image('enorme.jpg', 1000, 1000)->size(3000)])
        ->assertSessionHasErrors(['image' => 'A imagem pode ter no máximo 2 MB.']);
    $this->post('/meu-site/imagens/capa', ['image' => UploadedFile::fake()->image('x.png', 100, 100)])->assertNotFound();

    $operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $operator->id, 'role' => 'operator']);
    $this->actingAs($operator)->post('/meu-site/imagens/logo', ['image' => UploadedFile::fake()->image('logo.png', 200, 200)])->assertForbidden();

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

test('a replaced draft image is removed, while the published one stays until the next publication', function () {
    $this->post('/meu-site/imagens/hero', ['image' => UploadedFile::fake()->image('a.jpg', 800, 500)]);
    $published = draftImage($this->tenant, 'hero');
    publishSiteWith([]);

    $this->post('/meu-site/imagens/hero', ['image' => UploadedFile::fake()->image('b.jpg', 800, 500)]);
    $replaced = draftImage($this->tenant, 'hero');
    $this->post('/meu-site/imagens/hero', ['image' => UploadedFile::fake()->image('c.jpg', 800, 500)]);

    Storage::disk('local')->assertExists("websites/{$this->tenant->id}/{$published}");
    Storage::disk('local')->assertMissing("websites/{$this->tenant->id}/{$replaced}");

    $this->delete('/meu-site/imagens/hero')->assertRedirect('/meu-site');
    $this->post('/meu-site/publicar');

    expect(Storage::disk('local')->allFiles())->toBe([]);
    $this->get("/climatizacao/clima-total/midia/{$published}")->assertNotFound();
});

test('the home shows the segment theme, the service region, the request actions and Open Graph image', function () {
    AssistantSetting::create(['tenant_id' => $this->tenant->id, 'enabled' => true, 'display_name' => 'Clima Total', 'service_region' => 'Campinas e região', 'daily_message_limit' => 100, 'daily_token_limit' => 1000, 'retention_days' => 30]);
    $this->post('/meu-site/imagens/hero', ['image' => UploadedFile::fake()->image('foto.jpg', 1000, 600)]);
    $hero = draftImage($this->tenant, 'hero');
    publishSiteWith([$this->service->id]);

    $this->get('/climatizacao/clima-total')->assertOk()
        ->assertSee('--site-tone: #E0F2FE', false)
        ->assertSee('Atendemos: Campinas e região')
        ->assertSee('href="https://vetorsmart.com.br/climatizacao/clima-total/solicitar"', false)
        ->assertSee('href="https://vetorsmart.com.br/climatizacao/clima-total/solicitar?servico='.$this->service->id.'"', false)
        ->assertSee('Agendar online')
        ->assertSee('R$ 250,00')
        ->assertSee('<meta property="og:image" content="https://vetorsmart.com.br/climatizacao/clima-total/midia/'.$hero.'">', false)
        ->assertSee('"areaServed":"Campinas e região"', false);
});

test('prices can be hidden from the site without hiding the services', function () {
    publishSiteWith([$this->service->id], showPrices: false);

    $this->get('/climatizacao/clima-total/servicos')->assertOk()->assertSee('Instalação de split')->assertDontSee('R$ 250,00');
});

test('a visitor request enters the triage queue of the right company, with the chosen published service', function () {
    $hidden = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Serviço não publicado']);
    publishSiteWith([$this->service->id]);
    auth()->logout();

    $this->get('/climatizacao/clima-total/solicitar?servico='.$this->service->id)->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<option value="'.$this->service->id.'" selected>', false)
        ->assertDontSee('Serviço não publicado');

    $this->post('/climatizacao/clima-total/solicitar', [
        'name' => 'Maria Souza', 'phone' => '(19) 98888-7777', 'email' => 'maria@example.com',
        'service_id' => $this->service->id, 'description' => 'Instalar um split de 12 mil BTUs.', 'consent' => '1',
    ])->assertRedirect('https://vetorsmart.com.br/climatizacao/clima-total/solicitar');

    $request = ServiceRequest::query()->sole();
    expect($request->tenant_id)->toBe($this->tenant->id)
        ->and($request->origin)->toBe(RequestOrigin::PublicSite)
        ->and($request->status)->toBe(RequestStatus::Triaged)
        ->and($request->service_id)->toBe($this->service->id)
        ->and($request->description)->toContain('Instalar um split')
        ->and(Customer::query()->sole()->only(['tenant_id', 'name', 'phone']))->toBe(['tenant_id' => $this->tenant->id, 'name' => 'Maria Souza', 'phone' => '19988887777']);

    $this->get('/climatizacao/clima-total/solicitar')->assertSee('Solicitação recebida.');

    $this->post('/climatizacao/clima-total/solicitar', ['name' => 'João', 'phone' => '19977776666', 'service_id' => $hidden->id, 'description' => 'Outro pedido aqui.', 'consent' => '1']);
    expect(ServiceRequest::query()->latest('id')->first()->only(['status', 'service_id']))->toBe(['status' => RequestStatus::Requested, 'service_id' => null]);
});

test('the request form refuses invalid data, ignores robots and limits repeated sends', function () {
    publishSiteWith([]);
    auth()->logout();

    $this->from('/climatizacao/clima-total/solicitar')->post('/climatizacao/clima-total/solicitar', ['name' => 'Ana', 'phone' => '123', 'description' => 'Preciso de ajuda.'])
        ->assertSessionHasErrors(['phone' => 'Informe um telefone com DDD.', 'consent' => 'Autorize o contato para enviar a solicitação.']);

    $this->post('/climatizacao/clima-total/solicitar', ['name' => 'Robô', 'phone' => '19977776666', 'description' => 'Spam spam spam.', 'consent' => '1', 'website' => 'https://spam.example'])
        ->assertRedirect()->assertSessionHas('site_request_sent', true);
    expect(ServiceRequest::query()->count())->toBe(0);

    $valid = ['name' => 'Ana Lima', 'phone' => '19977776666', 'description' => 'Preciso de manutenção.', 'consent' => '1'];
    $this->post('/climatizacao/clima-total/solicitar', $valid)->assertRedirect();
    $this->post('/climatizacao/clima-total/solicitar', $valid)->assertStatus(429);
});

test('the request form does not exist for a site that is not live, and is never in the sitemap', function () {
    $this->get('/climatizacao/clima-total/solicitar')->assertNotFound();
    $this->post('/climatizacao/clima-total/solicitar', ['name' => 'Ana Lima', 'phone' => '19977776666', 'description' => 'Preciso de ajuda.', 'consent' => '1'])->assertNotFound();
    expect(ServiceRequest::query()->count())->toBe(0);

    publishSiteWith([$this->service->id]);

    $this->get('/sitemap.xml')->assertOk()->assertSee('/climatizacao/clima-total/servicos', false)->assertDontSee('solicitar', false)->assertDontSee('midia', false);
});

test('on the company own domain the request form and the published images are served, and nothing else is opened', function () {
    $this->post('/meu-site/imagens/logo', ['image' => UploadedFile::fake()->image('logo.png', 300, 300)]);
    $logo = draftImage($this->tenant, 'logo');
    publishSiteWith([]);
    TenantDomain::query()->create(['tenant_id' => $this->tenant->id, 'hostname' => 'www.climatotal.com.br', 'kind' => 'custom', 'status' => 'verified', 'is_primary' => true, 'verified_at' => now()]);
    app(TrustedHosts::class)->forgetCache();
    auth()->logout();

    $this->get('http://www.climatotal.com.br/solicitar')->assertOk()->assertSee('action="https://www.climatotal.com.br/solicitar"', false);
    $this->get("http://www.climatotal.com.br/midia/{$logo}")->assertOk();
    $this->get('http://www.climatotal.com.br/midia/logo-nao-existe.webp')->assertNotFound();
    $this->get('http://www.climatotal.com.br/meu-site/previa')->assertNotFound();
    URL::forceRootUrl('http://localhost');
});

test('a request from the site follows triage and scheduling, respecting the professional availability', function () {
    $this->service->update(['duration_minutes' => 60]);
    $professional = Professional::factory()->create(['tenant_id' => $this->tenant->id]);
    $professional->services()->attach($this->service->id, ['tenant_id' => $this->tenant->id]);
    ProfessionalAvailability::create(['tenant_id' => $this->tenant->id, 'professional_id' => $professional->id, 'weekday' => 1, 'start_time' => '08:00:00', 'end_time' => '12:00:00']);
    $monday = CarbonImmutable::now()->next(CarbonImmutable::MONDAY);
    publishSiteWith([$this->service->id]);
    auth()->logout();

    $this->post('/climatizacao/clima-total/solicitar', ['name' => 'Maria Souza', 'phone' => '19988887777', 'service_id' => $this->service->id, 'description' => 'Instalar um split.', 'consent' => '1']);
    $request = ServiceRequest::query()->sole();

    $this->actingAs($this->owner)->post(route('tenant.requests.schedule', $request), ['professional_id' => $professional->id, 'starts_at' => $monday->setTime(14, 0)->format('Y-m-d H:i')])
        ->assertSessionHasErrors();
    expect($request->fresh()->status)->toBe(RequestStatus::Triaged);

    $this->actingAs($this->owner)->post(route('tenant.requests.schedule', $request), ['professional_id' => $professional->id, 'starts_at' => $monday->setTime(9, 0)->format('Y-m-d H:i')])
        ->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe(RequestStatus::Scheduled)
        ->and(Appointment::query()->where('service_request_id', $request->id)->where('tenant_id', $this->tenant->id)->exists())->toBeTrue();
});
