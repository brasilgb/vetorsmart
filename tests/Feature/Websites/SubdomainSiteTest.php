<?php

use App\Enums\TenantStatus;
use App\Models\AssistantConversation;
use App\Models\AssistantSetting;
use App\Models\Segment;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Websites\TenantSubdomainService;
use App\Services\Websites\TrustedHosts;
use Database\Seeders\SegmentSeeder;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

/**
 * A request that arrives on the given hostname. The central host is restored afterwards for relative requests.
 */
function onSubdomain(string $host, string $path = '/', string $method = 'GET'): TestResponse
{
    $response = $method === 'GET' ? test()->get('http://'.$host.$path) : test()->post('http://'.$host.$path);

    URL::forceRootUrl('http://localhost');

    return $response;
}

/**
 * Publish the signed-in company's site with the given headline.
 */
function publishSubdomainSite(string $headline): void
{
    test()->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => $headline])->assertRedirect('/meu-site');
    test()->post('/meu-site/publicar')->assertRedirect('/meu-site');
}

/**
 * An active company with a published site, its owner and its subdomain.
 *
 * @return array{tenant: Tenant, owner: User}
 */
function companyWithSubdomain(string $name, string $slug, Segment $segment): array
{
    $tenant = Tenant::factory()->create(['name' => $name, 'slug' => $slug, 'segment_id' => $segment->id]);
    TenantSetting::factory()->create(['tenant_id' => $tenant->id, 'business_name' => $name]);
    $owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    app(TenantSubdomainService::class)->assign($tenant);

    return ['tenant' => $tenant, 'owner' => $owner];
}

beforeEach(function () {
    config(['platform.subdomain_sites' => true]);

    $this->segment = Segment::factory()->create(['name' => 'Climatização', 'slug' => 'climatizacao', 'active' => true]);
    $company = companyWithSubdomain('Clima Total', 'clima-total', $this->segment);
    $this->tenant = $company['tenant'];
    $this->owner = $company['owner'];

    $this->actingAs($this->owner);
});

test('a registered company receives a unique subdomain derived from its slug', function () {
    $this->seed(SegmentSeeder::class);
    auth()->logout();

    $this->post(route('register.store'), [
        'company_name' => 'Clima Total',
        'segment_id' => Segment::query()->where('slug', 'limpeza')->value('id'),
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect();

    $tenant = Tenant::query()->latest('id')->firstOrFail();

    // "climatotal" already belongs to the first Clima Total, so the new company gets the next free name.
    expect(app(TenantSubdomainService::class)->current($tenant)?->hostname)->toBe('climatotal2.vetorsmart.test')
        ->and(app(TenantSubdomainService::class)->current($this->tenant)?->hostname)->toBe('climatotal.vetorsmart.test');
});

test('the current subdomain serves the site, its services and its assistant, with itself as canonical', function () {
    $service = Service::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Instalação']);
    $this->patch('/meu-site/rascunho', ['section' => 'services', 'service_ids' => [$service->id], 'show_professionals' => false, 'professional_ids' => []]);
    AssistantSetting::create(['tenant_id' => $this->tenant->id, 'enabled' => true, 'display_name' => 'Clima Total', 'daily_message_limit' => 100, 'daily_token_limit' => 1000, 'retention_days' => 30]);
    publishSubdomainSite('Bem-vindo à Clima Total');

    onSubdomain('climatotal.vetorsmart.test')->assertOk()
        ->assertSee('Bem-vindo à Clima Total', false)
        ->assertSee('<link rel="canonical" href="https://climatotal.vetorsmart.test/"', false)
        ->assertSee('href="https://climatotal.vetorsmart.test/servicos"', false)
        ->assertSee('href="https://climatotal.vetorsmart.test/atendimento"', false);
    onSubdomain('climatotal.vetorsmart.test', '/servicos')->assertOk()->assertSee('Instalação', false);
    onSubdomain('climatotal.vetorsmart.test', '/atendimento')->assertOk();
    onSubdomain('climatotal.vetorsmart.test', '/atendimento/conversa', 'POST')->assertOk();
    expect(AssistantConversation::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);

    onSubdomain('climatotal.vetorsmart.test', '/sitemap.xml')->assertOk()->assertSee('<loc>https://climatotal.vetorsmart.test/</loc>', false);
    onSubdomain('climatotal.vetorsmart.test', '/robots.txt')->assertOk()->assertSee('Sitemap: https://climatotal.vetorsmart.test/sitemap.xml', false);

    // The standard address keeps answering during the transition, pointing search engines to the subdomain.
    $this->get('/climatizacao/clima-total')->assertOk()->assertSee('<link rel="canonical" href="https://climatotal.vetorsmart.test/"', false);
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('climatizacao/clima-total', false);
});

test('a subdomain never exposes the administrative area or the address of another company', function () {
    publishSubdomainSite('Site da Clima Total');

    foreach (['/dashboard', '/login', '/meu-site', '/meu-site/previa', '/rootadmin', '/climatizacao/clima-total', '/up/../dashboard'] as $path) {
        onSubdomain('climatotal.vetorsmart.test', $path)->assertNotFound();
    }

    $response = onSubdomain('climatotal.vetorsmart.test');

    foreach ($response->headers->getCookies() as $cookie) {
        expect($cookie->getDomain())->toBeNull();
    }
});

test('an unknown, unpublished or suspended subdomain answers a plain 404 without company data', function () {
    $other = companyWithSubdomain('Frio Bom', 'frio-bom', $this->segment)['tenant'];

    $unknown = onSubdomain('naoexiste.vetorsmart.test');
    expect($unknown->getStatusCode())->toBe(404)->and($unknown->getContent())->not->toContain('Clima Total');

    // Frio Bom exists but has not published its site.
    onSubdomain('friobom.vetorsmart.test')->assertNotFound();

    publishSubdomainSite('Site da Clima Total');
    $this->tenant->forceFill(['status' => TenantStatus::Suspended])->save();

    onSubdomain('climatotal.vetorsmart.test')->assertNotFound();
    onSubdomain('climatotal.vetorsmart.test', '/atendimento/conversa', 'POST')->assertStatus(503);
    expect($other->fresh()->status)->toBe(TenantStatus::Active);
});

test('each subdomain serves only its own company', function () {
    publishSubdomainSite('Site da Clima Total');

    $other = companyWithSubdomain('Frio Bom', 'frio-bom', $this->segment);
    $this->actingAs($other['owner']);
    publishSubdomainSite('Site do Frio Bom');

    onSubdomain('climatotal.vetorsmart.test')->assertOk()->assertSee('Site da Clima Total', false)->assertDontSee('Site do Frio Bom', false);
    onSubdomain('friobom.vetorsmart.test')->assertOk()->assertSee('Site do Frio Bom', false)->assertDontSee('Site da Clima Total', false);
});

test('an administrator changes the subdomain and the earlier one redirects permanently, keeping path and query', function () {
    publishSubdomainSite('Site da Clima Total');

    $this->patch('/meu-site/subdominio', ['subdomain' => 'clima-total-sp'])->assertRedirect('/meu-site')->assertSessionHasNoErrors();

    onSubdomain('clima-total-sp.vetorsmart.test')->assertOk()->assertSee('Site da Clima Total', false);
    onSubdomain('climatotal.vetorsmart.test', '/?utm_source=cartao')->assertStatus(301)->assertRedirect('https://clima-total-sp.vetorsmart.test/?utm_source=cartao');
    onSubdomain('climatotal.vetorsmart.test', '/atendimento')->assertStatus(301)->assertRedirect('https://clima-total-sp.vetorsmart.test/atendimento');

    $this->get('/meu-site')->assertInertia(fn ($page) => $page
        ->where('website.subdomain.hostname', 'clima-total-sp.vetorsmart.test')
        ->where('website.subdomain.previous', ['climatotal.vetorsmart.test'])
        ->where('website.canonical_url', 'https://clima-total-sp.vetorsmart.test/'));

    // The company may take back a name it used before.
    $this->patch('/meu-site/subdominio', ['subdomain' => 'climatotal'])->assertSessionHasNoErrors();
    expect(app(TenantSubdomainService::class)->current($this->tenant)?->hostname)->toBe('climatotal.vetorsmart.test');
});

test('a subdomain change refuses invalid, reserved and taken names, including names another company used before', function (string $name) {
    $other = companyWithSubdomain('Frio Bom', 'frio-bom', $this->segment)['tenant'];
    app(TenantSubdomainService::class)->change($other, 'frio-bom-antigo', User::factory()->create());
    app(TenantSubdomainService::class)->change($other, 'friobom', User::factory()->create());

    $this->patch('/meu-site/subdominio', ['subdomain' => $name])->assertSessionHasErrors('subdomain');

    expect(app(TenantSubdomainService::class)->current($this->tenant)?->hostname)->toBe('climatotal.vetorsmart.test');
})->with([
    'too short' => 'ab',
    'uppercase and accents' => 'Clímã',
    'leading hyphen' => '-clima',
    'double hyphen (punycode)' => 'xn--clima',
    'dot (nested subdomain)' => 'a.clima',
    'reserved' => 'admin',
    'reserved login' => 'login',
    'another company current' => 'friobom',
    'another company earlier' => 'frio-bom-antigo',
]);

test('subdomain changes are limited and reserved to who manages the company', function () {
    config(['platform.max_subdomain_changes' => 1]);

    $operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $this->tenant->id, 'user_id' => $operator->id, 'role' => 'operator']);

    $this->actingAs($operator)->patch('/meu-site/subdominio', ['subdomain' => 'outro-nome'])->assertForbidden();

    $this->actingAs($this->owner)->patch('/meu-site/subdominio', ['subdomain' => 'primeiro-nome'])->assertSessionHasNoErrors();
    $this->patch('/meu-site/subdominio', ['subdomain' => 'segundo-nome'])->assertSessionHasErrors('subdomain');

    expect(app(TenantSubdomainService::class)->current($this->tenant)?->hostname)->toBe('primeiro-nome.vetorsmart.test');
});

test('the platform subdomain cannot be removed or made primary as if it were a custom domain', function () {
    $domain = app(TenantSubdomainService::class)->current($this->tenant);

    $this->delete("/meu-site/dominios/{$domain->id}")->assertSessionHasErrors('domain');
    $this->post("/meu-site/dominios/{$domain->id}/principal")->assertSessionHasErrors('domain');

    expect(TenantDomain::query()->whereKey($domain->id)->exists())->toBeTrue();
});

test('with subdomain sites disabled the subdomain keeps redirecting to the standard address', function () {
    config(['platform.subdomain_sites' => false]);
    publishSubdomainSite('Site da Clima Total');

    onSubdomain('climatotal.vetorsmart.test', '/servicos?x=1')->assertStatus(301)->assertRedirect('https://vetorsmart.com.br/climatizacao/clima-total/servicos?x=1');
    $this->get('/climatizacao/clima-total')->assertOk()->assertSee('<link rel="canonical" href="https://vetorsmart.com.br/climatizacao/clima-total"', false);
    onSubdomain('climatotal.vetorsmart.test', '/atendimento/conversa', 'POST')->assertStatus(503);
});

test('a verified custom domain stays canonical while the subdomain remains an alternative', function () {
    publishSubdomainSite('Site da Clima Total');
    TenantDomain::query()->create(['tenant_id' => $this->tenant->id, 'hostname' => 'www.climatotal.com.br', 'kind' => 'custom', 'status' => 'verified', 'is_primary' => true, 'verified_at' => now()]);
    app(TrustedHosts::class)->forgetCache();

    onSubdomain('climatotal.vetorsmart.test')->assertOk()->assertSee('<link rel="canonical" href="https://www.climatotal.com.br/"', false);
    onSubdomain('climatotal.vetorsmart.test', '/sitemap.xml')->assertNotFound();
    onSubdomain('www.climatotal.com.br', '/sitemap.xml')->assertOk()->assertSee('<loc>https://www.climatotal.com.br/</loc>', false);
});

test('a homologation company is never indexable on its subdomain', function () {
    $this->tenant->forceFill(['name' => '[HOMOLOG] Clima Total'])->save();
    publishSubdomainSite('Site da Clima Total');

    onSubdomain('climatotal.vetorsmart.test')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    onSubdomain('climatotal.vetorsmart.test', '/sitemap.xml')->assertOk()->assertDontSee('<loc>', false);
});

test('the backfill command previews by default and only adds the missing subdomains', function () {
    $legacy = Tenant::factory()->create(['name' => 'Gelo Fino', 'slug' => 'gelo-fino', 'segment_id' => $this->segment->id]);

    $this->artisan('sites:assign-subdomains')->expectsOutputToContain('gelo-fino')->assertSuccessful();
    expect(app(TenantSubdomainService::class)->current($legacy))->toBeNull();

    $this->artisan('sites:assign-subdomains --execute')->expectsOutputToContain('gelofino.vetorsmart.test')->assertSuccessful();

    expect(app(TenantSubdomainService::class)->current($legacy)?->hostname)->toBe('gelofino.vetorsmart.test')
        ->and(TenantDomain::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

test('a subdomain taken by a simultaneous registration at the moment of the insert moves to the next free name', function () {
    $tenant = Tenant::factory()->create(['name' => 'Gelo Fino', 'slug' => 'gelo-fino', 'segment_id' => $this->segment->id]);
    $rival = Tenant::factory()->create(['name' => 'Rival', 'slug' => 'rival', 'segment_id' => $this->segment->id]);
    $raced = false;

    TenantDomain::creating(function (TenantDomain $domain) use (&$raced, $rival) {
        if (! $raced && $domain->tenant_id !== $rival->id) {
            $raced = true;
            TenantDomain::query()->create(['tenant_id' => $rival->id, 'hostname' => $domain->hostname, 'kind' => 'subdomain', 'status' => 'verified', 'is_primary' => false, 'verified_at' => now()]);
        }
    });

    // The rival row lives inside the failed savepoint and is rolled back with it; what matters is the retry.
    expect(app(TenantSubdomainService::class)->assign($tenant)->hostname)->toBe('gelofino2.vetorsmart.test');
});
