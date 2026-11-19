<?php

use App\Enums\BillingSource;
use App\Enums\RequestOrigin;
use App\Enums\SubscriptionStatus;
use App\Models\AssistantSetting;
use App\Models\BillingSetting;
use App\Models\PortalListing;
use App\Models\Segment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Services\Portal\PortalSearch;
use App\Services\Websites\TenantSubdomainService;
use App\Services\Websites\WebsiteService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

/**
 * PORTAL-01: Regional Portal on its own host, search by service and region over published and consented data only,
 * neutral ordering, consent in Meu Site and requests that keep the "Portal Regional" origin.
 */
beforeEach(function () {
    $this->climate = Segment::factory()->create(['name' => 'Climatização', 'slug' => 'climatizacao', 'active' => true]);
    $this->electric = Segment::factory()->create(['name' => 'Eletricistas', 'slug' => 'eletricistas', 'active' => true]);
});

/**
 * A company with a published site, built through the real services. Options: name, slug, segment, city, state,
 * services (names), about, region, listed, publish, homolog, phone.
 *
 * @param  array<string, mixed>  $options
 * @return array{tenant: Tenant, owner: User, services: list<Service>}
 */
function portalCompany(array $options): array
{
    $tenant = Tenant::factory()->state(($options['homolog'] ?? false) ? ['is_homolog' => true] : [])->create([
        'name' => $options['name'], 'slug' => $options['slug'], 'segment_id' => $options['segment']->id, 'phone' => $options['phone'] ?? '(19) 3333-0000',
    ]);
    TenantSetting::factory()->create(['tenant_id' => $tenant->id, 'business_name' => $options['name'], 'city' => $options['city'] ?? 'Campinas', 'state' => $options['state'] ?? 'SP']);
    $owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

    if (isset($options['region'])) {
        AssistantSetting::create(['tenant_id' => $tenant->id, 'enabled' => false, 'display_name' => $options['name'], 'service_region' => $options['region'], 'daily_message_limit' => 100, 'daily_token_limit' => 1000, 'retention_days' => 30]);
    }

    $services = array_map(fn (string $name): Service => Service::factory()->create(['tenant_id' => $tenant->id, 'name' => $name]), $options['services'] ?? []);
    $websites = app(WebsiteService::class);
    $websites->saveDraft($tenant, ['about' => $options['about'] ?? null, 'service_ids' => array_map(fn (Service $service): int => $service->id, $services)], $owner);

    if ($options['publish'] ?? true) {
        $websites->publish($tenant, $owner);
    }

    if ($options['listed'] ?? true) {
        $websites->setPortalListing($tenant, true, $owner);
    }

    return ['tenant' => $tenant, 'owner' => $owner, 'services' => $services];
}

/**
 * @param  array<string, string|int>  $query
 */
function portal(array $query = [], string $path = '/'): TestResponse
{
    $response = test()->get('http://'.config('platform.portal_host').$path.($query === [] ? '' : '?'.http_build_query($query)));
    URL::forceRootUrl('http://localhost');

    return $response;
}

/**
 * Names of the companies listed, in order.
 *
 * @return list<string>
 */
function listedNames(TestResponse $response): array
{
    preg_match_all('/<h3 id="empresa-\d+">([^<]+)<\/h3>/', (string) $response->getContent(), $matches);

    return array_map(fn (string $name): string => html_entity_decode($name), $matches[1]);
}

test('the portal answers on its own host, is never read as a company and the name is reserved', function () {
    portal()->assertOk()->assertSee('Qual serviço você precisa?')->assertDontSee('noindex', false);
    portal([], '/meu-site')->assertNotFound();
    portal([], '/login')->assertNotFound();
    portal([], '/solicitar')->assertNotFound();
    portal([], '/robots.txt')->assertOk()->assertSee('Sitemap: https://'.config('platform.portal_host').'/sitemap.xml');
    portal([], '/sitemap.xml')->assertOk()->assertSee('<loc>https://'.config('platform.portal_host').'/</loc>', false);

    expect(app(TenantSubdomainService::class)->problemWith('portal'))->toBe('Este endereço é reservado pela plataforma.');
});

test('the search by service ignores accents, case and extra spaces, and only lists companies offering it', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de ar-condicionado']]);
    portalCompany(['name' => 'Luz Forte', 'slug' => 'luz-forte', 'segment' => $this->electric, 'services' => ['Instalação de tomadas']]);

    expect(listedNames(portal(['q' => '  INSTALACAO   de AR condicionado '])))->toBe(['Clima Total'])
        ->and(listedNames(portal(['q' => 'instalações'])))->toEqualCanonicalizing(['Clima Total', 'Luz Forte'])
        ->and(listedNames(portal(['q' => 'eletricistas'])))->toBe(['Luz Forte']);

    portal(['q' => 'encanador'])->assertOk()->assertSee('Nenhum prestador encontrado');
});

test('the search by city and state matches the city first and then the service region, never another state', function () {
    portalCompany(['name' => 'Clima Valinhos', 'slug' => 'clima-valinhos', 'segment' => $this->climate, 'city' => 'Valinhos', 'region' => 'Campinas, Vinhedo e região', 'services' => ['Manutenção de ar-condicionado']]);
    portalCompany(['name' => 'Clima Campinas', 'slug' => 'clima-campinas', 'segment' => $this->climate, 'city' => 'Campinas', 'services' => ['Manutenção de ar-condicionado']]);
    portalCompany(['name' => 'Clima Sorocaba', 'slug' => 'clima-sorocaba', 'segment' => $this->climate, 'city' => 'Sorocaba', 'services' => ['Manutenção de ar-condicionado']]);
    portalCompany(['name' => 'Clima Campinas MG', 'slug' => 'clima-campinas-mg', 'segment' => $this->climate, 'city' => 'Campinas', 'state' => 'MG', 'services' => ['Manutenção de ar-condicionado']]);

    expect(listedNames(portal(['cidade' => 'campinas', 'uf' => 'sp'])))->toBe(['Clima Campinas', 'Clima Valinhos'])
        ->and(listedNames(portal(['uf' => 'MG'])))->toBe(['Clima Campinas MG']);
});

test('filters combine service, region, category and company name', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de ar-condicionado']]);
    portalCompany(['name' => 'Luz Forte', 'slug' => 'luz-forte', 'segment' => $this->electric, 'services' => ['Instalação de tomadas']]);
    portalCompany(['name' => 'Luz Norte', 'slug' => 'luz-norte', 'segment' => $this->electric, 'city' => 'Sorocaba', 'services' => ['Instalação de tomadas']]);

    expect(listedNames(portal(['q' => 'instalação', 'categoria' => 'eletricistas', 'cidade' => 'Campinas'])))->toBe(['Luz Forte'])
        ->and(listedNames(portal(['categoria' => 'eletricistas', 'nome' => 'norte'])))->toBe(['Luz Norte']);
});

test('only eligible companies appear: consent, published site, active company, real company, active segment and minimum data', function () {
    portalCompany(['name' => 'Visível', 'slug' => 'visivel', 'segment' => $this->climate, 'services' => ['Limpeza de split']]);
    portalCompany(['name' => 'Sem consentimento', 'slug' => 'sem-consentimento', 'segment' => $this->climate, 'listed' => false, 'services' => ['Limpeza de split']]);
    $draftOnly = portalCompany(['name' => 'Só rascunho', 'slug' => 'so-rascunho', 'segment' => $this->climate, 'publish' => false, 'services' => ['Limpeza de split']]);
    $suspended = portalCompany(['name' => 'Suspensa', 'slug' => 'suspensa', 'segment' => $this->climate, 'services' => ['Limpeza de split']]);
    portalCompany(['name' => 'Empresa de teste', 'slug' => 'empresa-teste', 'segment' => $this->climate, 'homolog' => true, 'services' => ['Limpeza de split']]);
    portalCompany(['name' => 'Sem cidade', 'slug' => 'sem-cidade', 'segment' => $this->climate, 'city' => '', 'services' => ['Limpeza de split']]);
    $retired = portalCompany(['name' => 'Retirada', 'slug' => 'retirada', 'segment' => $this->climate, 'services' => ['Limpeza de split']]);

    $suspended['tenant']->forceFill(['status' => 'suspended'])->save();
    app(WebsiteService::class)->unpublish($retired['tenant'], $retired['owner']);

    expect(listedNames(portal(['q' => 'limpeza'])))->toBe(['Visível']);

    $this->actingAs($draftOnly['owner'])->get('/meu-site')->assertInertia(fn ($page) => $page
        ->where('portal.listed', true)
        ->where('portal.issues', ['Publique o site: o portal mostra apenas o que está publicado.']));
});

test('while billing is in force a company whose subscription does not allow access leaves the portal', function () {
    $company = portalCompany(['name' => 'Inadimplente', 'slug' => 'inadimplente', 'segment' => $this->climate, 'services' => ['Limpeza de split']]);
    portalCompany(['name' => 'Em dia', 'slug' => 'em-dia', 'segment' => $this->climate, 'services' => ['Limpeza de split']]);
    app(SubscriptionService::class)->startTrial($company['tenant'], BillingSource::System)->forceFill(['status' => SubscriptionStatus::Suspended])->save();

    expect(listedNames(portal(['q' => 'limpeza'])))->toEqualCanonicalizing(['Inadimplente', 'Em dia']);

    config(['platform.billing_enforcement_enabled' => true]);
    BillingSetting::current()->forceFill(['enforcement_starts_at' => now()->subDay()])->save();

    expect(listedNames(portal(['q' => 'limpeza'])))->toBe(['Em dia']);
});

test('the portal shows only the published version, and never phones, e-mails or the draft', function () {
    $company = portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'about' => 'Texto publicado.', 'phone' => '(19) 3232-9898', 'services' => ['Instalação de split']]);
    $company['tenant']->forceFill(['email' => 'interno@climatotal.test'])->save();
    $hidden = Service::factory()->create(['tenant_id' => $company['tenant']->id, 'name' => 'Serviço em rascunho']);
    app(WebsiteService::class)->saveDraft($company['tenant'], ['about' => 'Texto do rascunho.', 'service_ids' => [$company['services'][0]->id, $hidden->id]], $company['owner']);

    portal(['q' => 'split'])->assertSee('Texto publicado.')->assertDontSee('Texto do rascunho.')->assertDontSee('Serviço em rascunho')
        ->assertDontSee('3232-9898')->assertDontSee('interno@climatotal.test');
    expect(listedNames(portal(['q' => 'rascunho'])))->toBe([]);
});

test('equivalent companies alternate by day, stay stable within a day and are not ordered by name or registration', function () {
    foreach (range(1, 6) as $index) {
        portalCompany(['name' => "Empresa {$index}", 'slug' => "empresa-{$index}", 'segment' => $this->climate, 'services' => ['Limpeza de split']]);
    }

    $order = fn (string $seed): array => app(PortalSearch::class)->search(['q' => 'limpeza'], 1, $seed)->getCollection()->pluck('business_name')->all();

    expect($order('2026-11-19'))->toBe($order('2026-11-19'));

    $orders = collect(['2026-11-19', '2026-11-20', '2026-11-21', '2026-11-22', '2026-11-23'])->map($order);
    $alphabetical = ['Empresa 1', 'Empresa 2', 'Empresa 3', 'Empresa 4', 'Empresa 5', 'Empresa 6'];

    expect($orders->unique(fn (array $names): string => implode('|', $names))->count())->toBeGreaterThan(1)
        ->and($orders->every(fn (array $names): bool => $names === $alphabetical))->toBeFalse()
        ->and($orders->map(fn (array $names): string => $names[0])->unique()->count())->toBeGreaterThan(1);
});

test('results are paginated on the server with a bounded page', function () {
    foreach (range(1, 13) as $index) {
        portalCompany(['name' => "Empresa {$index}", 'slug' => "empresa-{$index}", 'segment' => $this->climate, 'services' => ['Limpeza de split']]);
    }

    $first = portal(['q' => 'limpeza'])->assertSee('13 prestadores encontrados')->assertSee('pagina=2', false);
    $second = portal(['q' => 'limpeza', 'pagina' => 2]);

    expect(listedNames($first))->toHaveCount(12)
        ->and(listedNames($second))->toHaveCount(1)
        ->and(array_intersect(listedNames($first), listedNames($second)))->toBe([]);

    portal(['q' => 'limpeza', 'pagina' => 51])->assertStatus(422)->assertSee('Refine a busca para ver mais resultados.');
    portal(['uf' => 'XX'])->assertStatus(422)->assertSee('Escolha uma UF válida.');
    portal(['q' => str_repeat('a', 81)])->assertStatus(422);
});

test('result pages are not indexed, and the cards lead to the company site with the portal origin', function () {
    $company = portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $service = $company['services'][0];

    portal(['q' => 'split'])->assertHeader('X-Robots-Tag', 'noindex, follow')
        ->assertSee('href="https://vetorsmart.com.br/climatizacao/clima-total?origem=portal"', false)
        ->assertSee('href="https://vetorsmart.com.br/climatizacao/clima-total/solicitar?origem=portal&amp;servico='.$service->id.'"', false);

    $this->get('/climatizacao/clima-total?origem=portal')->assertOk()
        ->assertSee('href="https://vetorsmart.com.br/climatizacao/clima-total/solicitar?origem=portal&amp;servico='.$service->id.'"', false);
});

test('a request sent from a portal visit keeps the Portal Regional origin, in the right company and service', function () {
    $company = portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $service = $company['services'][0];

    $this->get('/climatizacao/clima-total/solicitar?origem=portal&servico='.$service->id)->assertOk()->assertSee('<input type="hidden" name="origem" value="portal">', false);

    $this->post('/climatizacao/clima-total/solicitar', ['name' => 'Maria Souza', 'phone' => '19988887777', 'service_id' => $service->id, 'description' => 'Instalar um split.', 'consent' => '1', 'origem' => 'portal'])
        ->assertRedirect('https://vetorsmart.com.br/climatizacao/clima-total/solicitar?origem=portal');
    $this->post('/climatizacao/clima-total/solicitar', ['name' => 'João Lima', 'phone' => '19977776666', 'description' => 'Pedido direto no site.', 'consent' => '1']);
    $this->post('/climatizacao/clima-total/solicitar', ['name' => 'Ana', 'phone' => '19966665555', 'description' => 'Origem forjada.', 'consent' => '1', 'origem' => 'anuncio'])->assertSessionHasErrors('origem');

    $requests = ServiceRequest::query()->orderBy('id')->get();
    expect($requests)->toHaveCount(2)
        ->and($requests[0]->only(['tenant_id', 'origin', 'service_id']))->toBe(['tenant_id' => $company['tenant']->id, 'origin' => RequestOrigin::Portal, 'service_id' => $service->id])
        ->and($requests[1]->origin)->toBe(RequestOrigin::PublicSite)
        ->and(RequestOrigin::Portal->label())->toBe('Portal Regional');
});

test('the company controls its participation in Meu Site, with immediate effect, and only who manages the site can change it', function () {
    $company = portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'listed' => false, 'services' => ['Instalação de split']]);
    $operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $company['tenant']->id, 'user_id' => $operator->id, 'role' => 'operator']);

    $this->actingAs($operator)->patch('/meu-site/portal', ['listed' => true])->assertForbidden();
    expect(listedNames(portal(['q' => 'split'])))->toBe([]);

    $this->actingAs($company['owner'])->patch('/meu-site/portal', ['listed' => true])->assertRedirect('/meu-site');
    expect(listedNames(portal(['q' => 'split'])))->toBe(['Clima Total']);

    $this->actingAs($company['owner'])->patch('/meu-site/portal', ['listed' => false])->assertRedirect('/meu-site');
    expect(listedNames(portal(['q' => 'split'])))->toBe([])
        ->and(PortalListing::query()->count())->toBe(0);
});

test('a result page runs the same number of queries for one company or for a full page', function () {
    $count = function (): int {
        DB::flushQueryLog();
        DB::enableQueryLog();
        portal(['q' => 'limpeza'])->assertOk();
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    };

    portalCompany(['name' => 'Empresa 1', 'slug' => 'empresa-1', 'segment' => $this->climate, 'services' => ['Limpeza de split']]);
    $one = $count();

    foreach (range(2, 12) as $index) {
        portalCompany(['name' => "Empresa {$index}", 'slug' => "empresa-{$index}", 'segment' => $this->climate, 'services' => ['Limpeza de split']]);
    }

    expect($count())->toBe($one);
});
