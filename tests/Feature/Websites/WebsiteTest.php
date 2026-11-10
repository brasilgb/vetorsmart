<?php

use App\Enums\GrowthEventType;
use App\Enums\TenantStatus;
use App\Models\GrowthEvent;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Models\WebsiteVersion;
use App\Services\Websites\DomainVerifier;
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
 * Request a page as if it arrived on the given hostname.
 */
function onHost(string $host, string $path = '/'): TestResponse
{
    $response = test()->get('http://'.$host.$path);

    // The test client keeps the URL root of the last request; restore the central host for later relative requests.
    URL::forceRootUrl('http://localhost');

    return $response;
}

beforeEach(function () {
    fakeDomainVerifier(false);

    $this->owner = User::factory()->create();
    $this->operator = User::factory()->create();

    $this->tenant = Tenant::factory()->create(['name' => 'Clima Total']);
    TenantSetting::factory()->create([
        'tenant_id' => $this->tenant->id,
        'business_name' => 'Clima Total Ltda',
        'address' => 'Rua das Flores, 100',
        'city' => 'Campinas',
        'state' => 'SP',
    ]);
    TenantUser::factory()->owner()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->owner->id]);
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $this->operator->id, 'role' => 'operator']);

    $this->actingAs($this->owner);
});

test('a company gets a platform subdomain and a site draft', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain'])->assertRedirect('/meu-site');

    $domain = TenantDomain::query()->where('tenant_id', $this->tenant->id)->firstOrFail();

    expect($domain->hostname)->toBe('clima-total.vetorsmart.test')
        ->and($domain->isServing())->toBeTrue()
        ->and($domain->is_primary)->toBeTrue();
});

test('drafts are never visible to visitors until they are published', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);

    $this->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Rascunho secreto', 'about' => 'Texto em rascunho'])
        ->assertRedirect('/meu-site');

    onHost('clima-total.vetorsmart.test')->assertNotFound();

    expect(str_contains(onHost('clima-total.vetorsmart.test')->getContent(), 'Rascunho secreto'))->toBeFalse();

    $this->post('/meu-site/publicar')->assertRedirect('/meu-site');

    onHost('clima-total.vetorsmart.test')
        ->assertOk()
        ->assertSee('Rascunho secreto', false);
});

test('a later draft edit does not change the published page until a new publication', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Versão um']);
    $this->post('/meu-site/publicar');

    $this->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Versão dois']);

    onHost('clima-total.vetorsmart.test')->assertSee('Versão um', false)->assertDontSee('Versão dois', false);

    $this->post('/meu-site/publicar');

    onHost('clima-total.vetorsmart.test')->assertSee('Versão dois', false);
    expect(WebsiteVersion::query()->where('tenant_id', $this->tenant->id)->count())->toBe(2);
});

test('retiring the site takes it offline but keeps its published history', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->post('/meu-site/publicar');

    $this->post('/meu-site/retirar')->assertRedirect('/meu-site');

    onHost('clima-total.vetorsmart.test')->assertNotFound();
    expect(WebsiteVersion::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('a suspended company is not served even with a verified domain and published site', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->post('/meu-site/publicar');

    $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

    onHost('clima-total.vetorsmart.test')->assertNotFound();
});

test('a visitor hitting an unknown hostname receives a generic refusal and no company data', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->post('/meu-site/publicar');

    $response = onHost('outra-empresa.vetorsmart.test');

    expect($response->getStatusCode())->toBeIn([400, 404])
        ->and($response->getContent())->not->toContain('Clima Total');
});

test('a pending custom domain serves nothing until its DNS record is verified', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->post('/meu-site/publicar');

    $this->post('/meu-site/dominios', ['kind' => 'custom', 'hostname' => 'www.climatotal.com.br'])->assertRedirect('/meu-site');

    $custom = TenantDomain::query()->where('hostname', 'www.climatotal.com.br')->firstOrFail();
    expect($custom->status->value)->toBe('pending');

    onHost('www.climatotal.com.br')->assertNotFound();

    fakeDomainVerifier(true);
    $this->post("/meu-site/dominios/{$custom->id}/verificar")->assertRedirect('/meu-site');

    expect($custom->fresh()->isServing())->toBeTrue();
});

test('a verified custom domain redirects to the primary address when it is not primary', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->post('/meu-site/publicar');

    fakeDomainVerifier(true);
    $this->post('/meu-site/dominios', ['kind' => 'custom', 'hostname' => 'climatotal.com.br']);
    $custom = TenantDomain::query()->where('hostname', 'climatotal.com.br')->firstOrFail();
    $this->post("/meu-site/dominios/{$custom->id}/verificar");

    onHost('climatotal.com.br')
        ->assertRedirect('https://clima-total.vetorsmart.test/');
});

test('a company cannot claim platform names, reserved subdomains or another company domain', function () {
    $this->post('/meu-site/dominios', ['kind' => 'custom', 'hostname' => 'app.vetorsmart.test'])->assertSessionHasErrors('hostname');
    $this->post('/meu-site/dominios', ['kind' => 'custom', 'hostname' => 'localhost'])->assertSessionHasErrors('hostname');
    $this->post('/meu-site/dominios', ['kind' => 'custom', 'hostname' => 'nao-e-dominio'])->assertSessionHasErrors('hostname');
    $this->post('/meu-site/dominios', ['kind' => 'custom', 'hostname' => '10.0.0.1'])->assertSessionHasErrors('hostname');

    $this->post('/meu-site/dominios', ['kind' => 'custom', 'hostname' => 'loja.example.com.br']);
    $this->post('/meu-site/dominios', ['kind' => 'custom', 'hostname' => 'loja.example.com.br'])->assertSessionHasErrors('hostname');

    expect(TenantDomain::query()->where('hostname', 'loja.example.com.br')->count())->toBe(1);
});

test('the number of domains per company is limited', function () {
    config(['platform.max_domains_per_tenant' => 2]);

    $this->post('/meu-site/dominios', ['kind' => 'custom', 'hostname' => 'a.example.com.br']);
    $this->post('/meu-site/dominios', ['kind' => 'custom', 'hostname' => 'b.example.com.br']);
    $this->post('/meu-site/dominios', ['kind' => 'custom', 'hostname' => 'c.example.com.br'])->assertSessionHasErrors('hostname');

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

test('a site renders only the selected active services of its own company', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);

    $selected = Service::factory()->fixed('150.00')->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação visível']);
    Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Serviço não selecionado']);
    Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Serviço inativo', 'active' => false]);

    $this->patch('/meu-site/rascunho', ['section' => 'services', 'service_ids' => [$selected->id], 'show_professionals' => false, 'professional_ids' => []]);
    $this->post('/meu-site/publicar');

    onHost('clima-total.vetorsmart.test')
        ->assertSee('Instalação visível', false)
        ->assertDontSee('Serviço não selecionado', false)
        ->assertDontSee('Serviço inativo', false)
        ->assertSee('R$ 150,00', false);
});

test('the authenticated preview shows the draft, is never indexed and is not served on the company domain', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Prévia interna']);

    $this->get('/meu-site/previa')
        ->assertOk()
        ->assertSee('Prévia interna', false)
        ->assertSee('noindex', false);

    onHost('clima-total.vetorsmart.test', '/meu-site/previa')->assertNotFound();
});

test('a guest cannot open the preview', function () {
    auth()->logout();

    $this->get('/meu-site/previa')->assertRedirect('/login');
});

test('user-provided text is escaped and never executed as markup', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => '<script>alert(1)</script>']);
    $this->post('/meu-site/publicar');

    $body = onHost('clima-total.vetorsmart.test')->getContent();

    expect($body)->not->toContain('<script>alert(1)</script>')
        ->and($body)->toContain('&lt;script&gt;alert(1)&lt;/script&gt;');
});

test('the published page carries title, description, canonical and Open Graph, and sitemap and robots follow indexing', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->patch('/meu-site/rascunho', ['section' => 'seo', 'seo' => ['title' => 'Clima Total | Campinas', 'description' => 'Instalação e manutenção de ar-condicionado.', 'indexable' => true]]);
    $this->post('/meu-site/publicar');

    onHost('clima-total.vetorsmart.test')
        ->assertSee('<title>Clima Total | Campinas</title>', false)
        ->assertSee('name="description"', false)
        ->assertSee('rel="canonical" href="https://clima-total.vetorsmart.test/"', false)
        ->assertSee('property="og:title"', false);

    onHost('clima-total.vetorsmart.test', '/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>https://clima-total.vetorsmart.test/</loc>', false);

    onHost('clima-total.vetorsmart.test', '/robots.txt')
        ->assertOk()
        ->assertSee('Sitemap: https://clima-total.vetorsmart.test/sitemap.xml', false);
});

test('a site marked as not indexable is hidden from search engines', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->patch('/meu-site/rascunho', ['section' => 'seo', 'seo' => ['title' => null, 'description' => null, 'indexable' => false]]);
    $this->post('/meu-site/publicar');

    onHost('clima-total.vetorsmart.test')->assertSee('noindex', false);
    onHost('clima-total.vetorsmart.test', '/robots.txt')->assertSee('Disallow: /', false);
});

test('an operator can read Meu Site but cannot change or publish it', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);

    $this->actingAs($this->operator)->get('/meu-site')->assertOk();
    $this->actingAs($this->operator)->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Indevido'])->assertForbidden();
    $this->actingAs($this->operator)->post('/meu-site/publicar')->assertForbidden();
});

test('publishing and first configuration are recorded as growth events, without personal data', function () {
    $this->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Configurado']);
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->post('/meu-site/publicar');

    expect(GrowthEvent::query()->where('tenant_id', $this->tenant->id)->where('event_type', GrowthEventType::WebsiteConfigured)->count())->toBe(1)
        ->and(GrowthEvent::query()->where('tenant_id', $this->tenant->id)->where('event_type', GrowthEventType::WebsitePublished)->count())->toBe(1)
        ->and(json_encode(GrowthEvent::query()->get()->toArray()))->not->toContain('Configurado');
});

test('the host of one company resolves only to its own content', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Site da Clima Total']);
    $this->post('/meu-site/publicar');

    $other = Tenant::factory()->create(['name' => 'Elétrica Sul']);
    TenantSetting::factory()->create(['tenant_id' => $other->id, 'business_name' => 'Elétrica Sul']);
    $otherOwner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $other->id, 'user_id' => $otherOwner->id]);

    $this->actingAs($otherOwner)->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->actingAs($otherOwner)->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Site da Elétrica Sul']);
    $this->actingAs($otherOwner)->post('/meu-site/publicar');

    onHost('clima-total.vetorsmart.test')->assertSee('Site da Clima Total', false)->assertDontSee('Site da Elétrica Sul', false);
    onHost('eletrica-sul.vetorsmart.test')->assertSee('Site da Elétrica Sul', false)->assertDontSee('Site da Clima Total', false);
});

test('an unpublished site is still reachable for its owner through the preview', function () {
    $this->post('/meu-site/dominios', ['kind' => 'subdomain']);
    $this->post('/meu-site/publicar');
    $this->post('/meu-site/retirar');

    $this->get('/meu-site/previa')->assertOk();
    onHost('clima-total.vetorsmart.test')->assertNotFound();
});
