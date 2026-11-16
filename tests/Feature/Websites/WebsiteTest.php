<?php

use App\Enums\GrowthEventType;
use App\Enums\TenantStatus;
use App\Models\AssistantConversation;
use App\Models\AssistantSetting;
use App\Models\GrowthEvent;
use App\Models\Segment;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\WebsiteVersion;
use App\Services\SegmentService;
use App\Services\Websites\DomainVerifier;
use App\Services\Websites\ReservedPaths;
use App\Services\Websites\TrustedHosts;
use App\Services\Websites\WebsiteResolver;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

/**
 * Test double for DNS: the TXT record is considered present only when the test says so.
 */
function fakeDomainVerifier(bool $present): void
{
    app()->instance(DomainVerifier::class, new class($present) implements DomainVerifier
    {
        public function __construct(private bool $present) {}

        public function hasVerificationRecord(string $hostname, string $expectedValue): bool
        {
            return $this->present;
        }
    });
}

/**
 * Request a page as if it arrived on an earlier company hostname (platform subdomain or custom domain).
 */
function onHost(string $host, string $path = '/', string $method = 'GET'): TestResponse
{
    $response = $method === 'GET' ? test()->get('http://'.$host.$path) : test()->post('http://'.$host.$path);

    // The test client keeps the URL root of the last request; restore the central host for later relative requests.
    URL::forceRootUrl('http://localhost');

    return $response;
}

/**
 * Request the official public address of a company site, on the central host.
 */
function visitSite(string $site = 'climatizacao/clima-total', string $suffix = ''): TestResponse
{
    return test()->get('/'.$site.$suffix);
}

/**
 * A second active company with an owner, in the given segment.
 *
 * @return array{tenant: Tenant, owner: User}
 */
function anotherCompany(string $name, string $slug, Segment $segment): array
{
    $tenant = Tenant::factory()->create(['name' => $name, 'slug' => $slug, 'segment_id' => $segment->id]);
    TenantSetting::factory()->create(['tenant_id' => $tenant->id, 'business_name' => $name]);
    $owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

    return ['tenant' => $tenant, 'owner' => $owner];
}

/**
 * Publish the draft of the signed-in company with the given headline.
 */
function publishWithHeadline(string $headline): void
{
    test()->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => $headline])->assertRedirect('/meu-site');
    test()->post('/meu-site/publicar')->assertRedirect('/meu-site');
}

/**
 * A verified custom domain of the company, accepted as a trusted host.
 */
function verifiedCustomDomain(Tenant $tenant, string $hostname, bool $primary): TenantDomain
{
    $domain = TenantDomain::query()->create(['tenant_id' => $tenant->id, 'hostname' => $hostname, 'kind' => 'custom', 'status' => 'verified', 'is_primary' => $primary, 'verified_at' => now()]);
    app(TrustedHosts::class)->forgetCache();

    return $domain;
}

beforeEach(function () {
    fakeDomainVerifier(false);

    $this->owner = User::factory()->create();
    $this->admin = User::factory()->create();
    $this->operator = User::factory()->create();

    $this->segment = Segment::factory()->create(['name' => 'Climatização', 'slug' => 'climatizacao', 'active' => true]);
    $this->tenant = Tenant::factory()->create(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment_id' => $this->segment->id]);
    TenantSetting::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_name' => 'Clima Total Ltda',
        'address' => 'Rua das Flores, 100',
        'city' => 'Campinas',
        'state' => 'SP',
    ]);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    TenantUser::factory()->admin()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->admin->id]);
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id, 'role' => 'operator']);

    $this->actingAs($this->owner);
});

test('the official address is /{segment}/{company} on the main domain and needs no domain or DNS', function () {
    $this->get('/meu-site')->assertInertia(fn ($page) => $page
        ->where('website.public_url', 'https://vetorsmart.com.br/climatizacao/clima-total')
        ->where('website.visible', false));

    publishWithHeadline('Bem-vindo à Clima Total');

    visitSite()->assertOk()->assertSee('Bem-vindo à Clima Total', false);

    $this->get('/meu-site')->assertInertia(fn ($page) => $page->where('website.visible', true)->where('website.address_issue', null));
    expect(TenantDomain::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});

test('drafts are never visible to visitors until they are published', function () {
    $this->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Rascunho secreto', 'about' => 'Texto em rascunho'])
        ->assertRedirect('/meu-site');

    $response = visitSite();
    $response->assertNotFound();
    expect($response->getContent())->not->toContain('Rascunho secreto');

    $this->post('/meu-site/publicar')->assertRedirect('/meu-site');

    visitSite()->assertOk()->assertSee('Rascunho secreto', false);
});

test('a later draft edit does not change the published page until a new publication', function () {
    publishWithHeadline('Versão um');

    $this->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Versão dois']);

    visitSite()->assertSee('Versão um', false)->assertDontSee('Versão dois', false);

    $this->post('/meu-site/publicar');

    visitSite()->assertSee('Versão dois', false);
    expect(WebsiteVersion::query()->where('tenant_id', $this->tenant->id)->count())->toBe(2);
});

test('retiring the site takes it offline but keeps its published history', function () {
    $this->post('/meu-site/publicar');

    $this->post('/meu-site/retirar')->assertRedirect('/meu-site');

    visitSite()->assertNotFound();
    expect(WebsiteVersion::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('a suspended company is not served and its page reveals nothing', function () {
    publishWithHeadline('Site da Clima Total');

    $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();
    auth()->logout();

    $response = visitSite();
    $response->assertNotFound();
    expect($response->getContent())->not->toContain('Clima Total');

    visitSite(suffix: '/atendimento')->assertNotFound();
});

test('an inactive segment takes its sites offline and the panel explains why', function () {
    publishWithHeadline('Site da Clima Total');

    $this->segment->update(['active' => false]);

    visitSite()->assertNotFound();
    $this->get('/meu-site')->assertInertia(fn ($page) => $page
        ->where('website.visible', false)
        ->where('website.address_issue', 'O segmento da empresa está indisponível no momento. Fale com o suporte.'));
});

test('a company without a segment has no public address and the panel says so', function () {
    $this->tenant->update(['segment_id' => null]);

    $this->get('/meu-site')->assertInertia(fn ($page) => $page
        ->where('website.public_url', null)
        ->where('website.address_issue', fn ($issue) => str_contains($issue, 'segmento')));
});

test('unknown segments and companies receive a generic 404 with no company data', function () {
    publishWithHeadline('Site da Clima Total');
    auth()->logout();

    foreach (['inexistente/clima-total', 'climatizacao/inexistente', 'climatizacao/Clima-Total', 'Climatizacao/clima-total'] as $address) {
        $response = visitSite($address);

        $response->assertNotFound();
        expect($response->getContent())->not->toContain('Clima Total');
    }
});

test('a company is reached only through its own segment', function () {
    publishWithHeadline('Site da Clima Total');

    $electric = Segment::factory()->create(['slug' => 'eletricistas']);

    visitSite('eletricistas/clima-total')->assertNotFound();
    visitSite('climatizacao/clima-total')->assertOk();
    expect($electric->exists)->toBeTrue();
});

test('two companies of the same segment each see only their own content', function () {
    publishWithHeadline('Site da Clima Total');

    $other = anotherCompany('Frio Bom', 'frio-bom', $this->segment);
    $this->actingAs($other['owner']);
    publishWithHeadline('Site da Frio Bom');

    visitSite('climatizacao/clima-total')->assertSee('Site da Clima Total', false)->assertDontSee('Site da Frio Bom', false);
    visitSite('climatizacao/frio-bom')->assertSee('Site da Frio Bom', false)->assertDontSee('Site da Clima Total', false);
});

test('two companies of different segments each see only their own content, and slugs stay unique', function () {
    publishWithHeadline('Site da Clima Total');

    $electric = Segment::factory()->create(['name' => 'Eletricistas', 'slug' => 'eletricistas']);
    $other = anotherCompany('Elétrica Sul', 'eletrica-sul', $electric);
    $this->actingAs($other['owner']);
    publishWithHeadline('Site da Elétrica Sul');

    visitSite('eletricistas/eletrica-sul')->assertSee('Site da Elétrica Sul', false)->assertDontSee('Site da Clima Total', false);
    visitSite('climatizacao/eletrica-sul')->assertNotFound();
    visitSite('eletricistas/clima-total')->assertNotFound();

    // The company slug is unique across the platform, so it is also unique inside every segment.
    expect(fn () => Tenant::factory()->create(['slug' => 'clima-total', 'segment_id' => $electric->id]))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('platform routes always win over site addresses and segments never take a reserved name', function () {
    $this->get('/clientes/novo')->assertOk();
    $this->get('/servicos')->assertOk()->assertInertia(fn ($page) => $page->component('tenant/services/index'));
    $this->get('/meu-site/previa')->assertOk();
    $this->get('/settings/profile')->assertOk();

    $actor = User::factory()->create(['is_root_admin' => true]);
    $service = app(SegmentService::class);

    foreach (['Clientes', 'Dashboard', 'Login', 'Meu Site', 'Build', 'Storage', 'Rootadmin'] as $name) {
        $segment = $service->create($name, null, true, $actor);

        expect(app(ReservedPaths::class)->isReserved($segment->slug))->toBeFalse();
    }
});

test('every first path segment of a platform route is reserved', function () {
    $reserved = app(ReservedPaths::class);

    foreach (['clientes', 'servicos', 'agenda', 'meu-site', 'dashboard', 'login', 'register', 'rootadmin', 'settings', 'api', 'atendimento', 'up', 'storage', 'build'] as $name) {
        expect($reserved->isReserved($name))->toBeTrue($name);
    }
});

test('a reserved name used as a segment slug is never resolved as a site', function () {
    $reservedSegment = Segment::factory()->create(['slug' => 'agenda', 'active' => true]);
    $tenant = Tenant::factory()->create(['slug' => 'algo', 'segment_id' => $reservedSegment->id]);

    expect(app(WebsiteResolver::class)->resolvePath('agenda', 'algo'))->toBeNull()
        ->and($tenant->exists)->toBeTrue();
});

test('the services page lists only the selected active services of its own company', function () {
    $selected = Service::factory()->fixed('150.00')->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação visível']);
    Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Serviço não selecionado']);
    Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Serviço inativo', 'active' => false]);

    $this->patch('/meu-site/rascunho', ['section' => 'services', 'service_ids' => [$selected->id], 'show_professionals' => false, 'professional_ids' => []]);
    $this->post('/meu-site/publicar');

    foreach (['', '/servicos'] as $suffix) {
        visitSite(suffix: $suffix)
            ->assertOk()
            ->assertSee('Instalação visível', false)
            ->assertDontSee('Serviço não selecionado', false)
            ->assertDontSee('Serviço inativo', false)
            ->assertSee('R$ 150,00', false);
    }

    visitSite(suffix: '/servicos')
        ->assertSee('rel="canonical" href="https://vetorsmart.com.br/climatizacao/clima-total/servicos"', false)
        ->assertSeeInOrder(['href="https://vetorsmart.com.br/climatizacao/clima-total/servicos"', 'aria-current="page"', 'Serviços</a>'], false);
});

test('the services page does not exist when no service is published', function () {
    $this->post('/meu-site/publicar');

    visitSite()->assertOk();
    visitSite(suffix: '/servicos')->assertNotFound();
});

test('the authenticated preview shows the draft, is never indexed and is not served on an earlier company domain', function () {
    TenantDomain::query()->create(['tenant_id' => $this->tenant->id, 'hostname' => 'clima-total.vetorsmart.test', 'kind' => 'subdomain', 'status' => 'verified', 'is_primary' => true, 'verified_at' => now()]);
    $this->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Prévia interna']);

    $this->get('/meu-site/previa')
        ->assertOk()
        ->assertSee('Prévia interna', false)
        ->assertSee('noindex', false);

    onHost('clima-total.vetorsmart.test', '/meu-site/previa')->assertNotFound();
});

test('a guest cannot open the preview, nor can a member of another company see this draft', function () {
    $this->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Rascunho reservado']);

    $other = anotherCompany('Frio Bom', 'frio-bom', $this->segment);
    $this->actingAs($other['owner'])->get('/meu-site/previa')->assertOk()->assertDontSee('Rascunho reservado', false);

    auth()->logout();
    $this->get('/meu-site/previa')->assertRedirect('/login');
});

test('user-provided text is escaped and never executed as markup', function () {
    publishWithHeadline('<script>alert(1)</script>');

    $body = visitSite()->getContent();

    expect($body)->not->toContain('<script>alert(1)</script>')
        ->and($body)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('the published page carries title, description, canonical and Open Graph on the official address', function () {
    $this->patch('/meu-site/rascunho', ['section' => 'seo', 'seo' => ['title' => 'Clima Total | Campinas', 'description' => 'Instalação e manutenção de ar-condicionado.', 'indexable' => true]]);
    $this->post('/meu-site/publicar');

    visitSite()
        ->assertSee('<title>Clima Total | Campinas</title>', false)
        ->assertSee('<meta name="description" content="Instalação e manutenção de ar-condicionado.">', false)
        ->assertSee('rel="canonical" href="https://vetorsmart.com.br/climatizacao/clima-total"', false)
        ->assertSee('property="og:url" content="https://vetorsmart.com.br/climatizacao/clima-total"', false)
        ->assertSee('property="og:title"', false)
        ->assertSee('"@context":"https://schema.org","@type":"ProfessionalService"', false)
        ->assertSee('"url":"https://vetorsmart.com.br/climatizacao/clima-total"', false)
        ->assertDontSee('__contextArgs', false)
        ->assertDontSee('noindex', false);
});

test('the central sitemap lists only live, indexable sites and the robots file points to it', function () {
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação']);
    $this->patch('/meu-site/rascunho', ['section' => 'services', 'service_ids' => [$service->id], 'show_professionals' => false, 'professional_ids' => []]);
    $this->post('/meu-site/publicar');

    $draft = anotherCompany('Rascunho Ltda', 'rascunho-ltda', $this->segment);
    $this->actingAs($draft['owner'])->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Ainda não']);

    $hidden = anotherCompany('Oculta Ltda', 'oculta-ltda', $this->segment);
    $this->actingAs($hidden['owner'])->patch('/meu-site/rascunho', ['section' => 'seo', 'seo' => ['title' => null, 'description' => null, 'indexable' => false]]);
    $this->post('/meu-site/publicar');

    $suspended = anotherCompany('Suspensa Ltda', 'suspensa-ltda', $this->segment);
    $this->actingAs($suspended['owner'])->post('/meu-site/publicar');
    $suspended['tenant']->forceFill(['status' => TenantStatus::Suspended])->save();

    auth()->logout();

    $sitemap = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->getContent();

    expect($sitemap)->toContain('<loc>https://vetorsmart.com.br/</loc>')
        ->toContain('<loc>https://vetorsmart.com.br/climatizacao/clima-total</loc>')
        ->toContain('<loc>https://vetorsmart.com.br/climatizacao/clima-total/servicos</loc>')
        ->not->toContain('rascunho-ltda')
        ->not->toContain('oculta-ltda')
        ->not->toContain('suspensa-ltda');

    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: https://vetorsmart.com.br/sitemap.xml', false)
        ->assertSee('Disallow: /meu-site/', false);

    visitSite('climatizacao/oculta-ltda')->assertOk()->assertSee('noindex', false);
});

test('an earlier platform subdomain redirects permanently to the official address and exposes nothing else', function () {
    TenantDomain::query()->create(['tenant_id' => $this->tenant->id, 'hostname' => 'clima-total.vetorsmart.test', 'kind' => 'subdomain', 'status' => 'verified', 'is_primary' => true, 'verified_at' => now()]);
    publishWithHeadline('Site da Clima Total');

    onHost('clima-total.vetorsmart.test')->assertStatus(301)->assertRedirect('https://vetorsmart.com.br/climatizacao/clima-total');
    onHost('clima-total.vetorsmart.test', '/atendimento')->assertStatus(301)->assertRedirect('https://vetorsmart.com.br/climatizacao/clima-total/atendimento');
    onHost('clima-total.vetorsmart.test', '/servicos')->assertStatus(301)->assertRedirect('https://vetorsmart.com.br/climatizacao/clima-total/servicos');
    onHost('clima-total.vetorsmart.test', '/robots.txt')->assertOk()->assertSee("Disallow:\n", false)->assertDontSee('Sitemap', false);

    onHost('clima-total.vetorsmart.test', '/sitemap.xml')->assertNotFound();
    onHost('clima-total.vetorsmart.test', '/atendimento-ia')->assertNotFound();
    onHost('clima-total.vetorsmart.test', '/clientes')->assertNotFound();
    onHost('clima-total.vetorsmart.test', '/climatizacao/clima-total')->assertNotFound();

    // The assistant is never served on a subdomain: no conversation can start there.
    onHost('clima-total.vetorsmart.test', '/atendimento/conversa', 'POST')->assertStatus(503);
    expect(AssistantConversation::query()->count())->toBe(0);
});

test('an earlier address of a site that is not live does not redirect', function () {
    TenantDomain::query()->create(['tenant_id' => $this->tenant->id, 'hostname' => 'clima-total.vetorsmart.test', 'kind' => 'subdomain', 'status' => 'verified', 'is_primary' => true, 'verified_at' => now()]);

    onHost('clima-total.vetorsmart.test')->assertNotFound();
    onHost('clima-total.vetorsmart.test', '/robots.txt')->assertSee('Disallow: /', false);

    $this->post('/meu-site/publicar');
    $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

    onHost('clima-total.vetorsmart.test')->assertNotFound();
});

test('a visitor hitting an unknown hostname receives a generic refusal and no company data', function () {
    publishWithHeadline('Site da Clima Total');

    $response = onHost('outra-empresa.vetorsmart.test');

    expect($response->getStatusCode())->toBeIn([400, 404])
        ->and($response->getContent())->not->toContain('Clima Total');
});

test('a custom domain is optional and serves nothing while it is pending', function () {
    publishWithHeadline('Site da Clima Total');

    visitSite()->assertOk()->assertSee('rel="canonical" href="https://vetorsmart.com.br/climatizacao/clima-total"', false);

    $this->post('/meu-site/dominios', ['hostname' => 'www.climatotal.com.br'])->assertRedirect('/meu-site');

    $custom = TenantDomain::query()->where('hostname', 'www.climatotal.com.br')->firstOrFail();
    expect($custom->status->value)->toBe('pending');

    expect(onHost('www.climatotal.com.br')->getStatusCode())->toBeIn([400, 404]);
    visitSite()->assertSee('rel="canonical" href="https://vetorsmart.com.br/climatizacao/clima-total"', false);
});

test('a verified custom domain serves the site itself, and both addresses share one canonical address', function () {
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação']);
    $this->patch('/meu-site/rascunho', ['section' => 'services', 'service_ids' => [$service->id], 'show_professionals' => false, 'professional_ids' => []]);
    publishWithHeadline('Site da Clima Total');
    verifiedCustomDomain($this->tenant, 'www.climatotal.com.br', primary: true);

    onHost('www.climatotal.com.br')
        ->assertOk()
        ->assertSee('Site da Clima Total', false)
        ->assertSee('rel="canonical" href="https://www.climatotal.com.br/"', false)
        ->assertSee('property="og:url" content="https://www.climatotal.com.br/"', false)
        ->assertSee('href="https://www.climatotal.com.br/servicos"', false)
        ->assertDontSee('noindex', false);

    onHost('www.climatotal.com.br', '/servicos')
        ->assertOk()
        ->assertSee('Instalação', false)
        ->assertSee('rel="canonical" href="https://www.climatotal.com.br/servicos"', false);

    // The standard address keeps working and points search engines to the same canonical address.
    visitSite()
        ->assertOk()
        ->assertSee('rel="canonical" href="https://www.climatotal.com.br/"', false)
        ->assertSee('href="https://vetorsmart.com.br/climatizacao/clima-total/servicos"', false);
    visitSite(suffix: '/servicos')->assertSee('rel="canonical" href="https://www.climatotal.com.br/servicos"', false);
});

test('a company domain has its own sitemap and robots, and the central sitemap does not list it twice', function () {
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação']);
    $this->patch('/meu-site/rascunho', ['section' => 'services', 'service_ids' => [$service->id], 'show_professionals' => false, 'professional_ids' => []]);
    publishWithHeadline('Site da Clima Total');
    verifiedCustomDomain($this->tenant, 'www.climatotal.com.br', primary: true);

    $other = anotherCompany('Frio Bom', 'frio-bom', $this->segment);
    $this->actingAs($other['owner']);
    publishWithHeadline('Site da Frio Bom');

    $central = $this->get('/sitemap.xml')->getContent();
    expect($central)->toContain('<loc>https://vetorsmart.com.br/climatizacao/frio-bom</loc>')
        ->not->toContain('clima-total')
        ->not->toContain('climatotal');

    $own = onHost('www.climatotal.com.br', '/sitemap.xml')->assertOk()->getContent();
    expect($own)->toContain('<loc>https://www.climatotal.com.br/</loc>')
        ->toContain('<loc>https://www.climatotal.com.br/servicos</loc>')
        ->not->toContain('frio-bom')
        ->not->toContain('vetorsmart.com.br');

    onHost('www.climatotal.com.br', '/robots.txt')
        ->assertOk()
        ->assertSee("Allow: /\n", false)
        ->assertSee('Sitemap: https://www.climatotal.com.br/sitemap.xml', false);
});

test('a secondary custom domain moves to the company main domain, never to the platform domain', function () {
    publishWithHeadline('Site da Clima Total');
    verifiedCustomDomain($this->tenant, 'www.climatotal.com.br', primary: true);
    verifiedCustomDomain($this->tenant, 'climatotal.com.br', primary: false);

    onHost('climatotal.com.br')->assertStatus(301)->assertRedirect('https://www.climatotal.com.br/');
    onHost('climatotal.com.br', '/servicos')->assertStatus(301)->assertRedirect('https://www.climatotal.com.br/servicos');
    onHost('climatotal.com.br', '/atendimento')->assertStatus(301)->assertRedirect('https://www.climatotal.com.br/atendimento');
    onHost('climatotal.com.br', '/sitemap.xml')->assertNotFound();
    onHost('www.climatotal.com.br')->assertOk();
});

test('a custom domain of a company that is not live serves nothing', function () {
    verifiedCustomDomain($this->tenant, 'www.climatotal.com.br', primary: true);

    onHost('www.climatotal.com.br')->assertNotFound();
    onHost('www.climatotal.com.br', '/robots.txt')->assertSee('Disallow: /', false);

    publishWithHeadline('Site da Clima Total');
    onHost('www.climatotal.com.br')->assertOk();

    $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();
    $response = onHost('www.climatotal.com.br');

    $response->assertNotFound();
    expect($response->getContent())->not->toContain('Clima Total');
});

test('a company domain exposes no administrative route and resolves only its own company', function () {
    publishWithHeadline('Site da Clima Total');
    verifiedCustomDomain($this->tenant, 'www.climatotal.com.br', primary: true);

    $other = anotherCompany('Frio Bom', 'frio-bom', $this->segment);
    $this->actingAs($other['owner']);
    publishWithHeadline('Site da Frio Bom');

    foreach (['/clientes', '/meu-site', '/meu-site/previa', '/login', '/dashboard', '/rootadmin', '/climatizacao/frio-bom', '/climatizacao/clima-total'] as $path) {
        onHost('www.climatotal.com.br', $path)->assertNotFound();
    }

    onHost('www.climatotal.com.br')->assertSee('Site da Clima Total', false)->assertDontSee('Site da Frio Bom', false);
});

test('the panel shows the standard address, the custom domain and the canonical address', function () {
    publishWithHeadline('Site da Clima Total');
    verifiedCustomDomain($this->tenant, 'www.climatotal.com.br', primary: true);

    $this->get('/meu-site')->assertInertia(fn ($page) => $page
        ->where('website.public_url', 'https://vetorsmart.com.br/climatizacao/clima-total')
        ->where('website.custom_url', 'https://www.climatotal.com.br/')
        ->where('website.canonical_url', 'https://www.climatotal.com.br/')
        ->where('website.visible', true));
});

test('new platform subdomains are no longer created', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain'])->assertSessionHasErrors('hostname');

    expect(TenantDomain::query()->where('tenant_id', $this->tenant->id)->exists())->toBeFalse();
});

test('a company cannot claim platform names, reserved subdomains or another company domain', function () {
    $this->post('/meu-site/dominios', ['hostname' => 'app.vetorsmart.test'])->assertSessionHasErrors('hostname');
    $this->post('/meu-site/dominios', ['hostname' => 'localhost'])->assertSessionHasErrors('hostname');
    $this->post('/meu-site/dominios', ['hostname' => 'nao-e-dominio'])->assertSessionHasErrors('hostname');
    $this->post('/meu-site/dominios', ['hostname' => '10.0.0.1'])->assertSessionHasErrors('hostname');

    $this->post('/meu-site/dominios', ['hostname' => 'loja.example.com.br']);
    $this->post('/meu-site/dominios', ['hostname' => 'loja.example.com.br'])->assertSessionHasErrors('hostname');

    expect(TenantDomain::query()->where('hostname', 'loja.example.com.br')->count())->toBe(1);
});

test('the number of domains per company is limited', function () {
    config(['platform.max_domains_per_tenant' => 2]);

    $this->post('/meu-site/dominios', ['hostname' => 'a.example.com.br']);
    $this->post('/meu-site/dominios', ['hostname' => 'b.example.com.br']);
    $this->post('/meu-site/dominios', ['hostname' => 'c.example.com.br'])->assertSessionHasErrors('hostname');

    expect(TenantDomain::query()->where('tenant_id', $this->tenant->id)->count())->toBe(2);
});

test('a domain of another company cannot be verified or made primary from this company', function () {
    $other = Tenant::factory()->create();
    $otherDomain = TenantDomain::query()->create(['tenant_id' => $other->id, 'hostname' => 'alheio.example.com.br', 'kind' => 'custom', 'status' => 'verified', 'verification_token' => null, 'is_primary' => false]);

    $this->post("/meu-site/dominios/{$otherDomain->id}/verificar")->assertNotFound();
    $this->post("/meu-site/dominios/{$otherDomain->id}/principal")->assertNotFound();
    $this->delete("/meu-site/dominios/{$otherDomain->id}")->assertNotFound();

    expect($otherDomain->fresh())->not->toBeNull();
});

test('an operator can read Meu Site but cannot change or publish it', function () {
    $this->actingAs($this->operator)->get('/meu-site')->assertOk();
    $this->actingAs($this->operator)->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Indevido'])->assertForbidden();
    $this->actingAs($this->operator)->post('/meu-site/publicar')->assertForbidden();
    $this->actingAs($this->operator)->post('/meu-site/retirar')->assertForbidden();
});

test('an admin can read, edit and publish Meu Site', function () {
    $this->actingAs($this->admin)->get('/meu-site')->assertOk();
    $this->actingAs($this->admin)
        ->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Administrado'])
        ->assertRedirect('/meu-site');
    $this->actingAs($this->admin)->post('/meu-site/publicar')->assertRedirect('/meu-site');

    expect(WebsiteVersion::query()->where('tenant_id', $this->tenant->id)->exists())->toBeTrue();
});

test('publishing and first configuration are recorded as growth events, without personal data', function () {
    publishWithHeadline('Configurado');

    expect(GrowthEvent::query()->where('tenant_id', $this->tenant->id)->where('event_type', GrowthEventType::WebsiteConfigured)->count())->toBe(1)
        ->and(GrowthEvent::query()->where('tenant_id', $this->tenant->id)->where('event_type', GrowthEventType::WebsitePublished)->count())->toBe(1)
        ->and(json_encode(GrowthEvent::query()->get()->toArray()))->not->toContain('Configurado');
});

test('an unpublished site is still reachable for its owner through the preview', function () {
    $this->post('/meu-site/publicar');
    $this->post('/meu-site/retirar');

    $this->get('/meu-site/previa')->assertOk();
    visitSite()->assertNotFound();
});

test('the site links its services page and its visitor assistant when they exist', function () {
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação']);
    $this->patch('/meu-site/rascunho', ['section' => 'services', 'service_ids' => [$service->id], 'show_professionals' => false, 'professional_ids' => []]);
    $this->post('/meu-site/publicar');

    visitSite()
        ->assertSee('href="https://vetorsmart.com.br/climatizacao/clima-total/servicos"', false)
        ->assertDontSee('/climatizacao/clima-total/atendimento', false);

    AssistantSetting::create(['tenant_id' => $this->tenant->id, 'enabled' => true, 'display_name' => 'Clima Total', 'daily_message_limit' => 100, 'daily_token_limit' => 1000, 'retention_days' => 30]);

    visitSite()->assertSee('href="https://vetorsmart.com.br/climatizacao/clima-total/atendimento"', false);
});
