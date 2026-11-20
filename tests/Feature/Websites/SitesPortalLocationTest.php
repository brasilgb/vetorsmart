<?php

use App\Enums\RequestOrigin;
use App\Models\AssistantSetting;
use App\Models\Municipality;
use App\Models\Segment;
use App\Models\Service;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\TenantWebsite;
use App\Models\User;
use App\Services\Portal\PortalReferral;
use App\Services\Websites\SegmentTheme;
use App\Services\Websites\WebsiteService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;

/**
 * Sites of the companies, Regional Portal by served area and automatic location (municipality of the IBGE registry).
 */
const MONTENEGRO = 4312401;
const SALVADOR_DO_SUL = 4316501;
const SAO_PEDRO_DA_SERRA = 4319356;

beforeEach(function () {
    Storage::fake('local');
    $this->segment = Segment::factory()->create(['name' => 'Climatização', 'slug' => 'climatizacao', 'active' => true]);
});

/**
 * A company based in Montenegro (RS), with published site and the given draft answers. Options: name, slug, services
 * (names), draft (extra draft keys), listed, homolog, address.
 *
 * @param  array<string, mixed>  $options
 * @return array{tenant: Tenant, owner: User, services: list<Service>}
 */
function siteCompany(Segment $segment, array $options = []): array
{
    $tenant = Tenant::factory()->state(($options['homolog'] ?? false) ? ['is_homolog' => true] : [])->create([
        'name' => $options['name'] ?? 'Frio Serra', 'slug' => $options['slug'] ?? 'frio-serra', 'segment_id' => $segment->id, 'phone' => '(51) 3632-0000', 'email' => 'contato@frioserra.test',
    ]);
    TenantSetting::factory()->create([
        'tenant_id' => $tenant->id, 'business_name' => $options['name'] ?? 'Frio Serra', 'city' => 'Montenegro', 'state' => 'RS',
        'address' => $options['address'] ?? 'Rua Ramiro Barcelos, 100', 'whatsapp' => '(51) 99999-0000',
        'business_hours' => ['1' => ['open' => '08:00', 'close' => '18:00'], '2' => ['open' => '08:00', 'close' => '18:00']],
    ]);
    $owner = User::factory()->create();
    TenantUser::factory()->owner()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $services = array_map(fn (string $name): Service => Service::factory()->fixed('180.00')->create(['tenant_id' => $tenant->id, 'name' => $name]), $options['services'] ?? ['Instalação de split', 'Limpeza de split', 'Recarga de gás']);
    $websites = app(WebsiteService::class);
    $websites->saveDraft($tenant, [
        'service_ids' => array_map(fn (Service $service): int => $service->id, $services),
        'base_municipality_id' => MONTENEGRO,
        'served_municipality_ids' => [SALVADOR_DO_SUL, SAO_PEDRO_DA_SERRA],
        'service_mode' => 'at_customer',
        'about' => 'Empresa de climatização com equipe própria.',
        ...($options['draft'] ?? []),
    ], $owner);
    $websites->publish($tenant, $owner);

    if ($options['listed'] ?? true) {
        $websites->setPortalListing($tenant, true, $owner);
    }

    return ['tenant' => $tenant, 'owner' => $owner, 'services' => $services];
}

/**
 * @param  array<string, string|int>  $query
 */
function portalPage(array $query = [], string $path = '/'): TestResponse
{
    $response = test()->get('http://'.config('platform.portal_host').$path.($query === [] ? '' : '?'.http_build_query($query)));
    URL::forceRootUrl('http://localhost');

    return $response;
}

function nominatimAnswers(array $address, int $status = 200): void
{
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response(['address' => $address], $status)]);
}

test('the IBGE registry is loaded and read with any spelling', function () {
    expect(Municipality::query()->count())->toBeGreaterThanOrEqual(5570)
        ->and(Municipality::query()->find(SALVADOR_DO_SUL)?->label())->toBe('Salvador do Sul, RS');

    $this->getJson('/localizacao/municipios?busca=salvador do')->assertOk()
        ->assertJsonFragment(['id' => SALVADOR_DO_SUL, 'label' => 'Salvador do Sul, RS', 'input' => 'Salvador do Sul - RS']);
    $this->getJson('/localizacao/municipios?busca=SAO PEDRO DA SER')->assertOk()->assertJsonFragment(['id' => SAO_PEDRO_DA_SERRA]);
    $this->getJson('/localizacao/municipios?busca=a')->assertOk()->assertExactJson(['municipalities' => []]);
});

test('the position becomes a municipality of the registry, and nothing about it is logged or returned', function () {
    Log::spy();
    nominatimAnswers(['village' => 'Salvador do Sul', 'state' => 'Rio Grande do Sul', 'ISO3166-2-lvl4' => 'BR-RS', 'country_code' => 'br']);

    $this->postJson('/localizacao/municipio', ['latitude' => -29.43712345, 'longitude' => -51.50987654])->assertOk()
        ->assertExactJson(['status' => 'found', 'municipality' => ['id' => SALVADOR_DO_SUL, 'name' => 'Salvador do Sul', 'state' => 'RS', 'label' => 'Salvador do Sul, RS', 'input' => 'Salvador do Sul - RS']]);

    // Only a rounded position reaches the provider (about 110 m), never the precise one.
    Http::assertSent(fn ($request): bool => str_contains($request->url(), 'lat=-29.437') && str_contains($request->url(), 'lon=-51.510') && ! str_contains($request->url(), '12345'));
    Log::shouldNotHaveReceived('info');
});

test('an ambiguous, foreign or failed answer asks the visitor to choose by hand, and the failure log has no position', function () {
    nominatimAnswers(['city' => 'Cidade Inexistente', 'ISO3166-2-lvl4' => 'BR-RS', 'country_code' => 'br']);
    $this->postJson('/localizacao/municipio', ['latitude' => -29.4, 'longitude' => -51.5])->assertOk()->assertJson(['status' => 'ambiguous', 'municipality' => null]);

    $this->postJson('/localizacao/municipio', ['latitude' => 48.85, 'longitude' => 2.35])->assertOk()->assertJson(['status' => 'outside']);
    $this->postJson('/localizacao/municipio', ['latitude' => 'abc'])->assertStatus(422)->assertJson(['status' => 'invalid']);
});

test('a provider failure is logged by its kind only, and the button can be turned off', function () {
    Log::spy();
    Http::fake(['nominatim.openstreetmap.org/*' => Http::response('erro', 503)]);
    $this->postJson('/localizacao/municipio', ['latitude' => -29.4, 'longitude' => -51.5])->assertOk()->assertJson(['status' => 'unavailable']);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'geolocation.provider_failed' && ! str_contains(json_encode($context), '29.4'))->once();

    config(['geolocation.provider' => 'none']);
    $this->postJson('/localizacao/municipio', ['latitude' => -29.4, 'longitude' => -51.5])->assertOk()->assertJson(['status' => 'unavailable']);
});

test('the location endpoints answer on the portal and on company hosts, and nothing else of the platform does', function () {
    nominatimAnswers(['town' => 'Montenegro', 'ISO3166-2-lvl4' => 'BR-RS', 'country_code' => 'br']);

    $this->postJson('http://'.config('platform.portal_host').'/localizacao/municipio', ['latitude' => -29.68, 'longitude' => -51.46])->assertOk()->assertJson(['status' => 'found']);
    $this->getJson('http://'.config('platform.portal_host').'/localizacao/municipios?busca=montene')->assertOk()->assertJsonFragment(['id' => MONTENEGRO]);
    $this->get('http://'.config('platform.portal_host').'/localizacao')->assertNotFound();
    URL::forceRootUrl('http://localhost');
});

test('a company based in Montenegro that serves Salvador do Sul appears in that search, with base and served area apart', function () {
    $company = siteCompany($this->segment);
    siteCompany($this->segment, ['name' => 'Outra Sem Cobertura', 'slug' => 'outra', 'draft' => ['served_municipality_ids' => []]]);

    $page = portalPage(['q' => 'split', 'cidade' => 'Salvador do Sul - RS'])->assertOk()
        ->assertSee('Sede em Montenegro, RS')
        ->assertSee('Atende em Salvador do Sul, RS')
        ->assertSee('value="Salvador do Sul - RS"', false)
        ->assertSee('&amp;municipio='.SALVADOR_DO_SUL, false)
        ->assertSee('Usar minha localização');

    expect($page->getContent())->toContain('Frio Serra')->not->toContain('Outra Sem Cobertura');

    // The served municipality has its own page and is in the sitemap; the base keeps its own.
    portalPage([], '/regioes/rs/salvador-do-sul')->assertOk()->assertSee('Frio Serra')->assertSee('Atende em Salvador do Sul, RS');
    portalPage([], '/sitemap.xml')->assertSee('/regioes/rs/salvador-do-sul<', false)->assertSee('/regioes/rs/montenegro<', false);

    // In Montenegro both are based there: no "Atende em" label.
    expect(substr_count((string) portalPage(['cidade' => 'Montenegro', 'uf' => 'RS', 'q' => 'split'])->getContent(), 'class="served-here"'))->toBe(0);

    // Leaving the portal, the site keeps the municipality up to the form, and the portal attribution.
    $this->get('/climatizacao/frio-serra?'.app(PortalReferral::class)->query($company['tenant']->id).'&municipio='.SALVADOR_DO_SUL)->assertOk();
    $this->get('/climatizacao/frio-serra/solicitar')->assertOk()->assertSee('value="Salvador do Sul - RS"', false)->assertSee('value="'.SALVADOR_DO_SUL.'"', false);
});

test('the portal tells apart an empty portal, a municipality without coverage and a search without match', function () {
    portalPage()->assertOk()->assertSee('O portal ainda não tem empresas participantes');
    portalPage(['q' => 'split', 'cidade' => 'Salvador do Sul - RS'])->assertOk()->assertSee('O portal ainda não tem empresas participantes');

    siteCompany($this->segment);

    portalPage(['q' => 'split', 'cidade' => 'Caxias do Sul - RS'])->assertOk()
        ->assertSee('Ainda não há empresas que atendem em Caxias do Sul, RS')
        ->assertSee('Trocar o município')
        ->assertSee('Ampliar a busca para Rio Grande do Sul');

    portalPage(['q' => 'encanamento', 'cidade' => 'Salvador do Sul - RS'])->assertOk()
        ->assertSee('Nenhuma empresa corresponde à sua busca')
        ->assertSee('Há empresas que atendem em Salvador do Sul, RS')
        ->assertSee('<a href="/">Limpar os filtros</a>', false);

    // Category and company name stay under "Mais filtros".
    portalPage()->assertSeeInOrder(['Qual serviço você precisa?', 'Em qual município?', 'Mais filtros', 'Categoria', 'Nome da empresa'], false);
});

test('a test company never appears in the portal, even serving the municipality', function () {
    siteCompany($this->segment, ['homolog' => true]);

    portalPage(['cidade' => 'Salvador do Sul - RS'])->assertOk()->assertDontSee('Frio Serra')->assertSee('O portal ainda não tem empresas participantes');
});

test('the site has header, hero, who we are, services, where we serve and footer, with the request button per service', function () {
    $company = siteCompany($this->segment, ['draft' => [
        'headline' => 'Ar-condicionado em Montenegro e região',
        'differentials' => ['Atendimento no mesmo dia'],
        'show_hours' => true,
        'trust_items' => [['label' => 'Certificação NR-10', 'source' => 'SENAI, 2023', 'url' => null], ['label' => 'Sem fonte', 'source' => '', 'url' => null]],
    ]]);
    [$install] = $company['services'];

    $this->get('/climatizacao/frio-serra')->assertOk()
        ->assertSeeInOrder(['Navegação do site', 'Serviços', 'Quem somos', 'Onde atendemos', 'Contato', 'Solicitar orçamento'], false)
        ->assertSee('<h1 id="titulo-principal">Ar-condicionado em Montenegro e região</h1>', false)
        ->assertSee('Sede em Montenegro, RS · atendemos 3 municípios da região')
        ->assertSee('class="hero-image hero-default"', false)
        ->assertSee('Certificação NR-10')
        ->assertSee('Fonte: SENAI, 2023', false)
        ->assertDontSee('Sem fonte')
        ->assertSee('Vamos até você: o atendimento acontece no seu endereço.')
        ->assertSee('Salvador do Sul, RS')
        ->assertSee('href="https://vetorsmart.com.br/climatizacao/frio-serra/solicitar?servico='.$install->id.'"', false)
        ->assertSee('R$ 180,00')
        // The address is published only by choice.
        ->assertDontSee('Rua Ramiro Barcelos')
        ->assertDontSee('Como chegar')
        ->assertSee('Segunda-feira')
        ->assertDontSee('Atendimento online');

    $html = (string) $this->get('/climatizacao/frio-serra')->getContent();
    preg_match('/<script type="application\/ld\+json">\s*(.+?)\s*<\/script>/s', $html, $match);
    $data = json_decode($match[1], true);

    expect($data['areaServed'])->toHaveCount(3)
        ->and($data['areaServed'][1])->toBe(['@type' => 'City', 'name' => 'Salvador do Sul', 'containedInPlace' => ['@type' => 'State', 'name' => 'RS']])
        ->and($data['address'])->not->toHaveKey('streetAddress')
        ->and($data['hasOfferCatalog']['itemListElement'])->toHaveCount(3)
        ->and($data['openingHoursSpecification'])->toHaveCount(2);

    // Suggested title and description when the company leaves them empty.
    expect($html)->toContain('<title>Frio Serra | Instalação de split em Montenegro - RS</title>')
        ->toContain('<meta name="description" content="Frio Serra: instalação de split, limpeza de split e recarga de gás em Montenegro e região. Solicite seu orçamento pelo site.">');
});

test('the address and "Como chegar" appear only when the company publishes them, and prices may be "Sob consulta"', function () {
    $company = siteCompany($this->segment, ['draft' => ['show_address' => true, 'show_phone' => false]]);
    [$install, $clean] = $company['services'];
    $this->actingAs($company['owner']);
    $this->patch('/meu-site/rascunho', ['section' => 'services', 'service_ids' => [$clean->id, $install->id], 'service_details' => [$install->id => ['description' => 'Instalação completa com suporte.', 'price_public' => false]]])->assertRedirect('/meu-site');
    $this->post('/meu-site/publicar');

    $this->get('/climatizacao/frio-serra')->assertOk()
        ->assertSee('Rua Ramiro Barcelos, 100, Montenegro, RS')
        ->assertSee('https://www.google.com/maps/search/?api=1&amp;query=Rua%20Ramiro%20Barcelos%2C%20100%2C%20Montenegro%2C%20RS', false)
        ->assertDontSee('(51) 3632-0000')
        ->assertSeeInOrder(['Limpeza de split', 'Instalação de split'])
        ->assertSee('Instalação completa com suporte.')
        ->assertSee('Sob consulta');
});

test('a single service keeps its own layout, and a company without photo gets the segment illustration', function () {
    siteCompany($this->segment, ['services' => ['Instalação de split']]);

    $this->get('/climatizacao/frio-serra')->assertOk()->assertSee('class="services single"', false)->assertSee('hero-default', false);
});

test('the main color always reaches 4.5:1 on white, and the palette sets the tone', function () {
    $light = SegmentTheme::colors(['accent_color' => '#FACC15', 'palette' => 'terra']);
    [$r, $g, $b] = sscanf($light['accent'], '#%02x%02x%02x');

    expect(SegmentTheme::contrastOnWhite($r, $g, $b))->toBeGreaterThanOrEqual(4.5)
        ->and($light['tone'])->toBe('#FEF3C7')
        ->and(SegmentTheme::colors(['accent_color' => '#1D4ED8'])['accent'])->toBe('#1D4ED8');

    foreach (SegmentTheme::PALETTES as $palette) {
        [$r, $g, $b] = sscanf($palette['accent'], '#%02x%02x%02x');
        expect(SegmentTheme::contrastOnWhite($r, $g, $b))->toBeGreaterThanOrEqual(4.5);
    }
});

test('the request keeps the confirmed municipality, warns outside the coverage and the panel shows it', function () {
    $company = siteCompany($this->segment);
    [$install] = $company['services'];

    $this->get('/climatizacao/frio-serra/solicitar?servico='.$install->id)->assertOk()
        ->assertSee('<option value="'.$install->id.'" selected>', false)
        ->assertSee('Município onde você precisa do serviço')
        ->assertSee('data-coverage="['.MONTENEGRO.','.SALVADOR_DO_SUL.','.SAO_PEDRO_DA_SERRA.']"', false)
        ->assertSee('Este município está fora da área informada por Frio Serra.');

    $payload = ['name' => 'Maria Souza', 'phone' => '51988887777', 'service_id' => $install->id, 'description' => 'Instalar um split na sala.', 'consent' => '1'];

    $this->post('/climatizacao/frio-serra/solicitar', [...$payload, 'municipality' => 'Cidade Inventada - RS'])->assertSessionHasErrors(['municipality' => 'Escolha o município na lista, por exemplo: Salvador do Sul - RS.']);
    $this->post('/climatizacao/frio-serra/solicitar', [...$payload, 'municipality' => 'Salvador do Sul - RS', 'municipality_id' => SALVADOR_DO_SUL])->assertSessionHasNoErrors();

    $request = ServiceRequest::query()->forTenant($company['tenant'])->latest('id')->firstOrFail();
    expect($request->municipality_id)->toBe(SALVADOR_DO_SUL)->and($request->origin)->toBe(RequestOrigin::PublicSite);

    // The hidden id never wins over a different text: the text the visitor sees is what counts.
    $this->post('/climatizacao/frio-serra/solicitar', [...$payload, 'municipality' => 'São Pedro da Serra - RS', 'municipality_id' => SALVADOR_DO_SUL]);
    expect(ServiceRequest::query()->forTenant($company['tenant'])->latest('id')->value('municipality_id'))->toBe(SAO_PEDRO_DA_SERRA);

    $this->actingAs($company['owner'])->get('/solicitacoes/'.$request->id)->assertOk()
        ->assertInertia(fn ($page) => $page->where('request.municipality', 'Salvador do Sul, RS'));
});

test('a request started in the portal keeps the portal origin and the municipality', function () {
    $company = siteCompany($this->segment);

    $this->get('/climatizacao/frio-serra/solicitar?'.app(PortalReferral::class)->query($company['tenant']->id).'&municipio='.SALVADOR_DO_SUL)->assertOk();
    $this->post('/climatizacao/frio-serra/solicitar', ['name' => 'João', 'phone' => '51977776666', 'description' => 'Limpeza do aparelho.', 'consent' => '1', 'municipality' => 'Salvador do Sul - RS'])->assertSessionHasNoErrors();

    $request = ServiceRequest::query()->forTenant($company['tenant'])->latest('id')->firstOrFail();
    expect($request->origin)->toBe(RequestOrigin::Portal)->and($request->municipality_id)->toBe(SALVADOR_DO_SUL);
});

test('Meu Site saves each question, keeps the order of the services and isolates the companies', function () {
    $company = siteCompany($this->segment);
    $other = siteCompany($this->segment, ['name' => 'Outra', 'slug' => 'outra-empresa']);
    [$install, $clean, $gas] = $company['services'];
    $this->actingAs($company['owner']);

    $this->get('/meu-site')->assertOk()->assertInertia(fn ($page) => $page
        ->where('location.base.id', MONTENEGRO)
        ->where('location.served.0.label', 'Salvador do Sul, RS')
        ->where('seoSuggestion.title', 'Frio Serra | Instalação de split em Montenegro - RS')
        ->has('options.palettes', 8));

    $this->patch('/meu-site/rascunho', ['section' => 'location', 'base_municipality_id' => MONTENEGRO, 'served_municipality_ids' => [SALVADOR_DO_SUL, MONTENEGRO]])->assertSessionHasNoErrors();
    $this->patch('/meu-site/rascunho', ['section' => 'location', 'base_municipality_id' => 999])->assertSessionHasErrors('base_municipality_id');
    $this->patch('/meu-site/rascunho', ['section' => 'mode', 'service_mode' => 'teleporte'])->assertSessionHasErrors('service_mode');
    $this->patch('/meu-site/rascunho', ['section' => 'mode', 'service_mode' => 'both'])->assertSessionHasNoErrors();
    $this->patch('/meu-site/rascunho', ['section' => 'services', 'service_ids' => [$gas->id, $other['services'][0]->id, $install->id]])->assertSessionHasNoErrors();
    $this->patch('/meu-site/rascunho', ['section' => 'colors', 'palette' => 'floresta', 'accent_color' => '#15803d', 'font' => 'serif'])->assertSessionHasNoErrors();
    $this->patch('/meu-site/rascunho', ['section' => 'contact', 'show_phone' => true, 'show_address' => true])->assertSessionHasNoErrors();
    $this->patch('/meu-site/rascunho', ['section' => 'about', 'about' => 'Texto', 'trust_items' => [['label' => 'Desde 2012', 'source' => '']]])->assertSessionHasErrors('trust_items.0.source');

    $draft = TenantWebsite::query()->forTenant($company['tenant'])->value('draft_content');
    expect($draft['served_municipality_ids'])->toBe([SALVADOR_DO_SUL])
        ->and($draft['service_mode'])->toBe('both')
        ->and($draft['service_ids'])->toBe([$gas->id, $install->id])
        ->and($draft['palette'])->toBe('floresta')
        ->and($draft['accent_color'])->toBe('#15803D')
        ->and($draft['show_address'])->toBeTrue()
        ->and($draft['show_email'])->toBeFalse();

    // A service photo goes only to a service of the company of the session.
    $this->post('/meu-site/servicos/'.$gas->id.'/foto', ['image' => UploadedFile::fake()->image('gas.jpg', 1200, 900)])->assertRedirect('/meu-site')->assertSessionHasNoErrors();
    $this->post('/meu-site/servicos/'.$other['services'][0]->id.'/foto', ['image' => UploadedFile::fake()->image('x.jpg', 800, 600)])->assertNotFound();
    $photo = TenantWebsite::query()->forTenant($company['tenant'])->value('draft_content')['service_details'][(string) $gas->id]['photo'];
    expect($photo['file'])->toMatch('/^service-[a-z0-9]{40}\.webp$/')->and($photo['width'])->toBe(900);

    // The photo is only in the preview until the publication, then served by the published version.
    $this->get('/meu-site/previa/midia/'.$photo['file'])->assertOk();
    $this->get('/climatizacao/frio-serra/midia/'.$photo['file'])->assertNotFound();
    $this->post('/meu-site/publicar');
    $this->get('/climatizacao/frio-serra/midia/'.$photo['file'])->assertOk();
    $this->get('/climatizacao/outra-empresa/midia/'.$photo['file'])->assertNotFound();
    $this->get('/climatizacao/frio-serra')->assertSee('midia/'.$photo['file'], false)->assertSeeInOrder(['Recarga de gás', 'Instalação de split'])->assertDontSee('Limpeza de split');

    // The preview on a phone frames the same draft and is never indexed.
    $this->get('/meu-site/previa?dispositivo=celular')->assertOk()->assertSee('<iframe src="http://localhost/meu-site/previa"', false)->assertSee('noindex, nofollow', false);

    // An operator does not edit the site.
    $operator = User::factory()->create();
    TenantUser::factory()->create(['tenant_id' => $company['tenant']->id, 'user_id' => $operator->id, 'role' => 'operator']);
    $this->actingAs($operator)->patch('/meu-site/rascunho', ['section' => 'mode', 'service_mode' => 'at_company'])->assertForbidden();
    $this->post('/meu-site/servicos/'.$gas->id.'/foto', ['image' => UploadedFile::fake()->image('gas.jpg', 600, 400)])->assertForbidden();
});

test('a site published before the service area keeps working, with its free-text region', function () {
    $company = siteCompany($this->segment, ['draft' => ['base_municipality_id' => null, 'served_municipality_ids' => [], 'service_mode' => null]]);
    AssistantSetting::create(['tenant_id' => $company['tenant']->id, 'enabled' => false, 'display_name' => 'Frio Serra', 'service_region' => 'Vale do Caí', 'daily_message_limit' => 100, 'daily_token_limit' => 1000, 'retention_days' => 30]);
    app(WebsiteService::class)->publish($company['tenant'], $company['owner']);

    $this->get('/climatizacao/frio-serra')->assertOk()->assertSee('Sede em Montenegro, RS · atendemos: Vale do Caí');
    $this->get('/climatizacao/frio-serra/solicitar')->assertOk()->assertSee('data-coverage="[]"', false);
});
