<?php

use App\Enums\BillingSource;
use App\Enums\RequestOrigin;
use App\Enums\RequestStatus;
use App\Enums\SubscriptionStatus;
use App\Models\Appointment;
use App\Models\AssistantSetting;
use App\Models\BillingSetting;
use App\Models\Customer;
use App\Models\PortalListing;
use App\Models\Professional;
use App\Models\Segment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Billing\SubscriptionService;
use App\Services\Portal\PortalSearch;
use App\Services\ServiceRequestService;
use App\Services\Websites\ReservedPaths;
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

    portal(['q' => 'encanador'])->assertOk()->assertSee('Nenhum profissional encontrado');
});

test('the search by city and state matches the city first and then the service region, never another state', function () {
    portalCompany(['name' => 'Clima Valinhos', 'slug' => 'clima-valinhos', 'segment' => $this->climate, 'city' => 'Valinhos', 'region' => 'Campinas, Vinhedo e região', 'services' => ['Manutenção de ar-condicionado']]);
    portalCompany(['name' => 'Clima Campinas', 'slug' => 'clima-campinas', 'segment' => $this->climate, 'city' => 'Campinas', 'services' => ['Manutenção de ar-condicionado']]);
    portalCompany(['name' => 'Clima Sorocaba', 'slug' => 'clima-sorocaba', 'segment' => $this->climate, 'city' => 'Sorocaba', 'services' => ['Manutenção de ar-condicionado']]);
    portalCompany(['name' => 'Clima Campinas MG', 'slug' => 'clima-campinas-mg', 'segment' => $this->climate, 'city' => 'Campinas', 'state' => 'MG', 'services' => ['Manutenção de ar-condicionado']]);

    expect(listedNames(portal([], '/regioes/sp/campinas')))->toBe(['Clima Campinas', 'Clima Valinhos'])
        ->and(listedNames(portal([], '/regioes/mg')))->toBe(['Clima Campinas MG'])
        ->and(listedNames(portal(['q' => 'manutencao', 'cidade' => 'campinas', 'uf' => 'sp'])))->toBe(['Clima Campinas', 'Clima Valinhos']);
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

    $first = portal(['q' => 'limpeza'])->assertSee('13 profissionais encontrados')->assertSee('pagina=2', false);
    $second = portal(['q' => 'limpeza', 'pagina' => 2]);

    expect(listedNames($first))->toHaveCount(12)
        ->and(listedNames($second))->toHaveCount(1)
        ->and(array_intersect(listedNames($first), listedNames($second)))->toBe([]);

    portal(['q' => 'limpeza', 'pagina' => 51])->assertStatus(422)->assertSee('Refine a busca para ver mais resultados.');
    portal(['uf' => 'XX'])->assertStatus(422)->assertSee('Escolha uma UF da lista.');
    portal(['q' => str_repeat('a', 81)])->assertStatus(422);
});

test('result pages are not indexed, and the cards lead to the company site with the portal origin', function () {
    $company = portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $service = $company['services'][0];

    portal(['q' => 'split'])->assertHeader('X-Robots-Tag', 'noindex, follow')
        ->assertSee('href="https://vetorsmart.com.br/climatizacao/clima-total?origem=portal&amp;servico='.$service->id.'"', false)
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

/*
| PORTAL-02: consumer experience.
*/

test('the home presents eligible professionals, only categories with companies, how it works, and is indexable', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    portalCompany(['name' => 'Fora do portal', 'slug' => 'fora', 'segment' => $this->electric, 'listed' => false, 'services' => ['Tomadas']]);

    $home = portal()->assertOk()->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('Encontre profissionais na sua região')
        ->assertSee('Encontrar profissionais')
        ->assertSee('Profissionais no portal')
        ->assertSee('Como funciona')
        ->assertSee('href="/categorias/climatizacao"', false)
        ->assertDontSee('href="/categorias/eletricistas"', false)
        ->assertSee('"@type":"WebSite"', false);

    expect(listedNames($home))->toBe(['Clima Total']);
});

test('the home shows six professionals and links to the full list, which is not indexed', function () {
    foreach (range(1, 7) as $index) {
        portalCompany(['name' => "Empresa {$index}", 'slug' => "empresa-{$index}", 'segment' => $this->climate, 'services' => ['Limpeza de split']]);
    }

    expect(listedNames(portal()->assertSee('href="/?pagina=1"', false)))->toHaveCount(6);
    expect(listedNames(portal(['pagina' => 1])->assertHeader('X-Robots-Tag', 'noindex, follow')))->toHaveCount(7);
});

test('active filters are shown, each one can be removed and all can be cleared, keeping the search in the URL', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);

    portal(['q' => 'split', 'cidade' => 'Campinas', 'uf' => 'SP'])->assertOk()
        ->assertSee('href="/regioes/sp/campinas" aria-label="Remover filtro Serviço: split"', false)
        ->assertSee('href="/regioes/sp?q=split" aria-label="Remover filtro Município: Campinas"', false)
        ->assertSee('<a class="clear" href="/"', false)
        ->assertSee('value="split"', false)
        ->assertSee('Oferece: Instalação de split');
});

test('an empty result offers wider searches, each removing one restriction', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);

    portal(['q' => 'split', 'cidade' => 'Sorocaba', 'uf' => 'SP'])->assertOk()
        ->assertSee('Nenhum profissional encontrado')
        ->assertSee('<a href="/regioes/sp?q=split">Ver todo o estado (SP)</a>', false)
        ->assertSee('<a href="/?pagina=1">Ver todos os profissionais do portal</a>', false);
});

test('only a category page with companies is indexable and listed in the sitemap; combined filters are not', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);

    portal([], '/categorias/climatizacao')->assertOk()->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('<link rel="canonical" href="https://'.config('platform.portal_host').'/categorias/climatizacao">', false)
        ->assertSee('<title>Climatização · Portal Regional VetorSmart</title>', false);
    portal([], '/categorias/eletricistas')->assertHeader('X-Robots-Tag', 'noindex, follow');
    portal(['q' => 'split'], '/categorias/climatizacao')->assertHeader('X-Robots-Tag', 'noindex, follow');

    portal([], '/sitemap.xml')->assertSee('/categorias/climatizacao', false)->assertDontSee('eletricistas', false);
});

test('a card without logo shows the segment placeholder, and "Ver profissional" keeps service and origin up to the request button', function () {
    $company = portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split', 'Limpeza']]);
    $other = portalCompany(['name' => 'Luz Forte', 'slug' => 'luz-forte', 'segment' => $this->electric, 'services' => ['Tomadas']]);
    $split = $company['services'][0];

    portal(['q' => 'instalação split'])->assertSee('class="avatar placeholder"', false)
        ->assertSee('href="https://vetorsmart.com.br/climatizacao/clima-total?origem=portal&amp;servico='.$split->id.'">Ver profissional', false);

    $this->get('/climatizacao/clima-total?origem=portal&servico='.$split->id)->assertOk()
        ->assertSee('<a class="cta" href="https://vetorsmart.com.br/climatizacao/clima-total/solicitar?origem=portal&amp;servico='.$split->id.'">Solicitar atendimento</a>', false);

    // A service of another company in the address never changes the company nor gets pre-selected.
    $this->get('/climatizacao/clima-total?origem=portal&servico='.$other['services'][0]->id)->assertOk()
        ->assertSee('<a class="cta" href="https://vetorsmart.com.br/climatizacao/clima-total/solicitar?origem=portal">Solicitar atendimento</a>', false);
});

/*
| PORTAL-03: friendly regional addresses, regional SEO and theme.
*/

test('query strings made of category, state and municipality move to the one friendly address', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $host = 'https://'.config('platform.portal_host');

    portal(['categoria' => 'climatizacao'])->assertRedirect($host.'/categorias/climatizacao')->assertStatus(301);
    portal(['uf' => 'sp'])->assertRedirect($host.'/regioes/sp');
    portal(['uf' => 'SP', 'cidade' => 'CAMPINAS ', 'categoria' => 'climatizacao', 'pagina' => 2])->assertRedirect($host.'/regioes/sp/campinas/climatizacao?pagina=2');
    portal([], '/regioes/SP/campinas')->assertRedirect($host.'/regioes/sp/campinas');

    // Free text, unknown places and combinations without a friendly address stay searches, never indexed.
    portal(['q' => 'split', 'uf' => 'SP'])->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
    portal(['uf' => 'SP', 'categoria' => 'climatizacao'])->assertOk()->assertHeader('X-Robots-Tag', 'noindex, follow');
    portal(['uf' => 'SP', 'cidade' => 'Sorocaba'])->assertOk()->assertSee('Nenhum profissional encontrado');
});

test('unknown categories, states and municipalities answer 404, and a municipality exists only where a participating company is based', function () {
    portalCompany(['name' => 'Clima Valinhos', 'slug' => 'clima-valinhos', 'segment' => $this->climate, 'city' => 'Valinhos', 'region' => 'Campinas e região', 'services' => ['Limpeza de split']]);
    portalCompany(['name' => 'Fora', 'slug' => 'fora', 'segment' => $this->climate, 'city' => 'Sorocaba', 'listed' => false, 'services' => ['Limpeza de split']]);

    portal([], '/categorias/nao-existe')->assertNotFound()->assertHeader('X-Robots-Tag', 'noindex, follow')->assertSee('Não há empresas participantes neste endereço');
    portal([], '/regioes/xx')->assertNotFound();
    portal([], '/regioes/sp/campinas')->assertNotFound();
    portal([], '/regioes/sp/sorocaba')->assertNotFound();
    portal([], '/regioes/sp/valinhos/nao-existe')->assertNotFound();
    portal([], '/regioes/sp/valinhos/a/b')->assertNotFound();
    portal([], '/regioes/sp/valinhos')->assertOk();
});

test('namesakes of different states have their own pages, and spellings of the same municipality share one', function () {
    portalCompany(['name' => 'Campinas Paulista', 'slug' => 'campinas-paulista', 'segment' => $this->climate, 'city' => 'Campinas', 'state' => 'SP', 'services' => ['Limpeza de split']]);
    portalCompany(['name' => 'Campinas Mineira', 'slug' => 'campinas-mineira', 'segment' => $this->climate, 'city' => 'Campinas', 'state' => 'MG', 'services' => ['Limpeza de split']]);
    portalCompany(['name' => 'Capital Um', 'slug' => 'capital-um', 'segment' => $this->climate, 'city' => 'São Paulo', 'services' => ['Limpeza de split']]);
    portalCompany(['name' => 'Capital Dois', 'slug' => 'capital-dois', 'segment' => $this->climate, 'city' => 'sao paulo', 'services' => ['Limpeza de split']]);

    expect(listedNames(portal([], '/regioes/sp/campinas')))->toBe(['Campinas Paulista'])
        ->and(listedNames(portal([], '/regioes/mg/campinas')))->toBe(['Campinas Mineira'])
        ->and(listedNames(portal([], '/regioes/sp/sao-paulo')))->toEqualCanonicalizing(['Capital Um', 'Capital Dois']);

    portal([], '/sitemap.xml')->assertSee('/regioes/sp/sao-paulo<', false)->assertDontSee('/regioes/sp/s%C3%A3o', false);
});

test('a municipality page shows the companies based there first and labels the ones that only serve it', function () {
    portalCompany(['name' => 'Clima Valinhos', 'slug' => 'clima-valinhos', 'segment' => $this->climate, 'city' => 'Valinhos', 'region' => 'Campinas, Vinhedo e região', 'services' => ['Limpeza de split']]);
    portalCompany(['name' => 'Clima Campinas', 'slug' => 'clima-campinas', 'segment' => $this->climate, 'city' => 'Campinas', 'services' => ['Limpeza de split']]);

    $page = portal([], '/regioes/sp/campinas')->assertOk()
        ->assertSee('Sediada em Valinhos - SP; atende Campinas conforme a região informada pela empresa.');

    expect(listedNames($page))->toBe(['Clima Campinas', 'Clima Valinhos'])
        ->and(substr_count((string) $page->getContent(), 'Sediada em'))->toBe(1);
});

test('friendly pages carry their own title, description, canonical, breadcrumbs and structured data; extra filters and later pages are not indexed', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $host = 'https://'.config('platform.portal_host');

    portal([], '/regioes/sp/campinas/climatizacao')->assertOk()->assertHeaderMissing('X-Robots-Tag')
        ->assertSee('<title>Climatização em Campinas - SP · Portal Regional VetorSmart</title>', false)
        ->assertSee('<meta name="description" content="Empresas de Climatização em Campinas - SP que participam do Portal Regional VetorSmart.', false)
        ->assertSee('<link rel="canonical" href="'.$host.'/regioes/sp/campinas/climatizacao">', false)
        ->assertSee('<a href="/regioes/sp">São Paulo</a>', false)
        ->assertSee('<a href="/regioes/sp/campinas">Campinas</a>', false)
        ->assertSee('"@type":"BreadcrumbList"', false)
        ->assertSee('"@type":"CollectionPage"', false)
        ->assertSee('"name":"Clima Total","url":"https://vetorsmart.com.br/climatizacao/clima-total"', false);

    portal([], '/regioes/sp')->assertOk()->assertSee('<title>Profissionais em São Paulo · Portal Regional VetorSmart</title>', false)->assertSee('Municípios em São Paulo');
    portal(['nome' => 'clima'], '/regioes/sp/campinas')->assertHeader('X-Robots-Tag', 'noindex, follow')->assertDontSee('"@type":"CollectionPage"', false);
    portal(['pagina' => 2], '/regioes/sp/campinas')->assertHeader('X-Robots-Tag', 'noindex, follow');
});

test('the sitemap lists only friendly pages with companies based there, without duplicates', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'city' => 'Campinas', 'services' => ['Instalação de split']]);
    portalCompany(['name' => 'Clima Dois', 'slug' => 'clima-dois', 'segment' => $this->climate, 'city' => 'Campinas', 'region' => 'Sorocaba', 'services' => ['Instalação de split']]);
    portalCompany(['name' => 'Fora', 'slug' => 'fora', 'segment' => $this->electric, 'city' => 'Jundiaí', 'listed' => false, 'services' => ['Tomadas']]);

    $sitemap = (string) portal([], '/sitemap.xml')->getContent();
    preg_match_all('/<loc>([^<]+)<\/loc>/', $sitemap, $matches);
    $host = 'https://'.config('platform.portal_host');

    expect($matches[1])->toBe([$host.'/', $host.'/categorias/climatizacao', $host.'/regioes/sp', $host.'/regioes/sp/campinas', $host.'/regioes/sp/campinas/climatizacao']);
});

test('friendly addresses exist only on the portal host and their names are reserved for segments', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);

    $this->get('/regioes/sp')->assertNotFound();
    $this->get('/categorias/climatizacao')->assertNotFound();

    expect(app(ReservedPaths::class)->isReserved('regioes'))->toBeTrue()
        ->and(app(ReservedPaths::class)->isReserved('categorias'))->toBeTrue();
});

test('cards on friendly pages keep the typed service and the portal origin, and the theme switch is offered', function () {
    $company = portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $service = $company['services'][0];

    portal(['q' => 'split'], '/regioes/sp/campinas')->assertOk()
        ->assertSee('value="split"', false)
        ->assertSee('href="https://vetorsmart.com.br/climatizacao/clima-total/solicitar?origem=portal&amp;servico='.$service->id.'"', false)
        ->assertSee('data-theme-switch hidden', false)
        ->assertSee('data-theme-option="auto"', false)
        ->assertSee("localStorage.getItem('portal-theme')", false);
});

/*
| PORTAL-04: origin, conversion and traceability of requests from the portal.
*/

/**
 * Send the request form of a company site as a visitor, from the portal or not, with an optional send token.
 *
 * @param  array<string, mixed>  $overrides
 */
function sendSiteRequest(string $site, array $overrides = []): TestResponse
{
    return test()->post('/'.$site.'/solicitar', [
        'name' => 'Maria Souza', 'phone' => '19988887777', 'description' => 'Preciso de uma instalação.', 'consent' => '1', 'origem' => 'portal',
        ...$overrides,
    ]);
}

test('the journey from the portal ends in the panel of the right company, with service, origin and status', function () {
    $company = portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $other = portalCompany(['name' => 'Luz Forte', 'slug' => 'luz-forte', 'segment' => $this->electric, 'services' => ['Tomadas']]);
    $service = $company['services'][0];

    // 1-3. Search, card, site: the links carry the service and the portal origin.
    $requestLink = 'https://vetorsmart.com.br/climatizacao/clima-total/solicitar?origem=portal&amp;servico='.$service->id;
    portal(['q' => 'split'])->assertSee('href="'.$requestLink.'"', false);

    // 4-5. The form keeps both, plus a one-time send token.
    $form = $this->get('/climatizacao/clima-total/solicitar?origem=portal&servico='.$service->id)->assertOk()
        ->assertSee('<input type="hidden" name="origem" value="portal">', false)
        ->assertSee('<option value="'.$service->id.'" selected>', false);
    preg_match('/name="envio" value="([A-Za-z0-9]{40})"/', (string) $form->getContent(), $token);

    // 6-7. Sent once, confirmed only after it is saved.
    sendSiteRequest('climatizacao/clima-total', ['service_id' => $service->id, 'envio' => $token[1]])
        ->assertRedirect('https://vetorsmart.com.br/climatizacao/clima-total/solicitar?origem=portal')
        ->assertSessionHas('site_request_sent', true);

    $request = ServiceRequest::query()->sole();
    expect($request->only(['tenant_id', 'service_id', 'origin', 'status']))->toBe([
        'tenant_id' => $company['tenant']->id, 'service_id' => $service->id, 'origin' => RequestOrigin::Portal, 'status' => RequestStatus::Triaged,
    ])->and($request->created_at)->not->toBeNull();

    // 8. The company sees it, marked as coming from the portal; another company never does.
    $this->actingAs($company['owner'])->get('/solicitacoes?origem=portal')->assertInertia(fn ($page) => $page
        ->where('filters.origem', 'portal')
        ->where('requests.data.0.id', $request->id)
        ->where('requests.data.0.origin', 'portal')
        ->where('requests.data.0.origin_label', 'Portal Regional'));
    $this->actingAs($company['owner'])->get('/solicitacoes/'.$request->id)->assertInertia(fn ($page) => $page->where('request.origin', 'Portal Regional')->where('request.origin_value', 'portal'));
    $this->actingAs($other['owner'])->get('/solicitacoes')->assertInertia(fn ($page) => $page->where('requests.data', []));
    $this->actingAs($other['owner'])->get('/solicitacoes/'.$request->id)->assertNotFound();
});

test('the company comes only from the address, and a service of another company is never linked', function () {
    $company = portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $other = portalCompany(['name' => 'Luz Forte', 'slug' => 'luz-forte', 'segment' => $this->electric, 'services' => ['Tomadas']]);

    sendSiteRequest('climatizacao/clima-total', ['service_id' => $other['services'][0]->id, 'tenant_id' => $other['tenant']->id])->assertRedirect();

    expect(ServiceRequest::query()->sole()->only(['tenant_id', 'service_id', 'origin']))
        ->toBe(['tenant_id' => $company['tenant']->id, 'service_id' => null, 'origin' => RequestOrigin::Portal]);
});

test('the same form sent twice creates one request, and a new form creates another', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $token = str_repeat('a', 40);

    sendSiteRequest('climatizacao/clima-total', ['envio' => $token])->assertSessionHas('site_request_sent', true);
    sendSiteRequest('climatizacao/clima-total', ['envio' => $token])->assertSessionHas('site_request_sent', true);
    expect(ServiceRequest::query()->count())->toBe(1);

    sendSiteRequest('climatizacao/clima-total', ['envio' => str_repeat('b', 40)]);
    expect(ServiceRequest::query()->count())->toBe(2);
});

test('a failed registration never confirms, and the same form can be sent again', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $token = str_repeat('c', 40);
    $this->mock(ServiceRequestService::class)->shouldReceive('create')->once()->andThrow(new RuntimeException('banco indisponível'));

    sendSiteRequest('climatizacao/clima-total', ['envio' => $token])->assertServerError()->assertSessionMissing('site_request_sent');
    expect(ServiceRequest::query()->count())->toBe(0);

    app()->forgetInstance(ServiceRequestService::class);
    sendSiteRequest('climatizacao/clima-total', ['envio' => $token])->assertSessionHas('site_request_sent', true);
    expect(ServiceRequest::query()->count())->toBe(1);
});

test('invalid data never confirms and keeps the send token and the portal origin for the corrected send', function () {
    portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $token = str_repeat('d', 40);

    $this->from('/climatizacao/clima-total/solicitar?origem=portal')
        ->post('/climatizacao/clima-total/solicitar', ['name' => 'Maria', 'phone' => '123', 'description' => 'Preciso de ajuda.', 'origem' => 'portal', 'envio' => $token])
        ->assertSessionHasErrors(['phone', 'consent'])->assertSessionMissing('site_request_sent');

    $this->get('/climatizacao/clima-total/solicitar?origem=portal')
        ->assertSee('name="envio" value="'.$token.'"', false)
        ->assertSee('<input type="hidden" name="origem" value="portal">', false)
        ->assertDontSee('Solicitação recebida.');
    expect(ServiceRequest::query()->count())->toBe(0);
});

test('the origin recorded at creation never changes along triage and scheduling', function () {
    $company = portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    sendSiteRequest('climatizacao/clima-total');
    $request = ServiceRequest::query()->sole();

    $this->actingAs($company['owner'])->patch(route('tenant.requests.triage', $request), ['service_id' => $company['services'][0]->id, 'origin' => 'panel'])->assertRedirect();

    expect($request->fresh()->origin)->toBe(RequestOrigin::Portal);
});

test('Meu Crescimento shows requests by origin from real records: received, scheduled and completed, only of the company', function () {
    $company = portalCompany(['name' => 'Clima Total', 'slug' => 'clima-total', 'segment' => $this->climate, 'services' => ['Instalação de split']]);
    $other = portalCompany(['name' => 'Luz Forte', 'slug' => 'luz-forte', 'segment' => $this->electric, 'services' => ['Tomadas']]);
    $tenant = $company['tenant'];
    $customer = Customer::factory()->create(['tenant_id' => $tenant->id]);
    $professional = Professional::factory()->create(['tenant_id' => $tenant->id]);
    $make = fn (RequestOrigin $origin, RequestStatus $status, array $extra = []): ServiceRequest => ServiceRequest::factory()->create(['tenant_id' => $tenant->id, 'customer_id' => $customer->id, 'origin' => $origin, 'status' => $status, ...$extra]);

    $make(RequestOrigin::Portal, RequestStatus::Requested);
    $scheduled = $make(RequestOrigin::Portal, RequestStatus::Scheduled);
    $done = $make(RequestOrigin::Portal, RequestStatus::Completed, ['closed_at' => now()]);
    foreach ([$scheduled, $done] as $item) {
        Appointment::factory()->create(['tenant_id' => $tenant->id, 'service_request_id' => $item->id, 'professional_id' => $professional->id]);
    }
    $make(RequestOrigin::PublicSite, RequestStatus::Requested);
    $make(RequestOrigin::Portal, RequestStatus::Requested, ['created_at' => now($tenant->timezone)->startOfMonth()->subDays(5)]);
    $otherCustomer = Customer::factory()->create(['tenant_id' => $other['tenant']->id]);
    ServiceRequest::factory()->count(4)->create(['tenant_id' => $other['tenant']->id, 'customer_id' => $otherCustomer->id, 'origin' => RequestOrigin::Portal]);

    $this->actingAs($company['owner'])->get('/meu-crescimento')->assertOk()->assertInertia(fn ($page) => $page
        ->where('origins', fn ($rows) => collect($rows)->firstWhere('origin', 'portal') == ['origin' => 'portal', 'label' => 'Portal Regional', 'received' => 3, 'scheduled' => 2, 'completed' => 1]
            && collect($rows)->firstWhere('origin', 'public_site')['received'] === 1)
        ->where('previousOrigins', fn ($rows) => collect($rows)->firstWhere('origin', 'portal')['received'] === 1));
});

test('the portal order ignores the volume of requests a company received', function () {
    $busy = portalCompany(['name' => 'Muito Procurada', 'slug' => 'muito-procurada', 'segment' => $this->climate, 'services' => ['Limpeza de split']]);
    portalCompany(['name' => 'Recém-chegada', 'slug' => 'recem-chegada', 'segment' => $this->climate, 'services' => ['Limpeza de split']]);
    $order = fn (): array => app(PortalSearch::class)->search(['q' => 'limpeza'], 1, '2026-11-19')->getCollection()->pluck('business_name')->all();
    $before = $order();

    $customer = Customer::factory()->create(['tenant_id' => $busy['tenant']->id]);
    ServiceRequest::factory()->count(15)->create(['tenant_id' => $busy['tenant']->id, 'customer_id' => $customer->id, 'origin' => RequestOrigin::Portal, 'status' => RequestStatus::Completed, 'closed_at' => now()]);

    expect($order())->toBe($before);
});
