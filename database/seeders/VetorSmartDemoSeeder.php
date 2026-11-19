<?php

namespace Database\Seeders;

use App\Enums\AssistantMessageRole;
use App\Enums\ConfirmationPolicy;
use App\Enums\MembershipStatus;
use App\Enums\PriceType;
use App\Enums\RequestOrigin;
use App\Enums\RequestPriority;
use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Enums\UserStatus;
use App\Models\AssistantSetting;
use App\Models\Customer;
use App\Models\Professional;
use App\Models\ProfessionalAvailability;
use App\Models\Segment;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\ServiceRequest;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AppointmentScheduler;
use App\Services\Assistant\AssistantConversations;
use App\Services\AuditLogger;
use App\Services\ServiceRequestService;
use App\Services\Websites\TenantSubdomainService;
use App\Services\Websites\WebsiteService;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demonstration base for visual and functional homologation (SEED-DEMO-01): three fictitious companies in Rio Grande
 * do Sul, each with an owner and an operator (e-mails under example.test), services, professionals, opening hours, a
 * published site listed in the Regional Portal, and about three months of requests and appointments.
 *
 * Everything goes through the real services (requests, triage, scheduling, rescheduling, completion with the declared
 * amount, cancellation, assignment, site publication, assistant hand-off) with the clock moved to the moment of each
 * step, so statuses, history, audit and Meu Crescimento come from the actual business rules. No totals are written.
 *
 * Safety: runs only in local or testing (refused in production even with --force); needs VETORSMART_DEMO_PASSWORD;
 * never deletes or truncates; leaves untouched any company or user that is not part of this set; no e-mail,
 * notification channel or HTTP call leaves the process; no subscription or charge is created. Running it again does
 * not duplicate anything: an existing demo company only has the password of its demo users refreshed.
 *
 *   php artisan db:seed --class=VetorSmartDemoSeeder
 */
class VetorSmartDemoSeeder extends Seeder
{
    /**
     * Mark kept in the company's settings (settings.demo_seed) to recognize the demonstration set.
     */
    public const string MARKER = 'seed-demo-01';

    public const string TIMEZONE = 'America/Sao_Paulo';

    /**
     * @var list<array{name: string, slug: string, segment: string, city: string, address: string, phone: string, whatsapp: string, owner: array{name: string, email: string}, operator: array{name: string, email: string}, accent: string, headline: string, about: string, differentials: list<string>, region: string, categories: array<string, list<array{name: string, description: string, minutes: int, price: string|null, type: PriceType}>>, professionals: list<string>, customers: list<string>}>
     */
    public const array COMPANIES = [
        [
            'name' => 'Silva Elétrica',
            'slug' => 'silva-eletrica',
            'segment' => 'eletricistas',
            'city' => 'Porto Alegre',
            'address' => 'Av. Demonstração, 100 · Bairro Exemplo',
            'phone' => '(51) 3000-0101',
            'whatsapp' => '(51) 90000-0101',
            'owner' => ['name' => 'Roberto Silva', 'email' => 'owner@silva-eletrica.example.test'],
            'operator' => ['name' => 'Patrícia Lima', 'email' => 'operador@silva-eletrica.example.test'],
            'accent' => '#D97706',
            'headline' => 'Instalações e reparos elétricos com segurança e garantia',
            'about' => 'Empresa fictícia de demonstração. Atendemos residências, comércios e condomínios em Porto Alegre e região com instalações elétricas, manutenção preventiva e atendimento de emergência.',
            'differentials' => ['Profissionais com NR-10', 'Orçamento sem compromisso', 'Garantia de 90 dias'],
            'region' => 'Porto Alegre, Canoas e Cachoeirinha',
            'categories' => [
                'Instalações' => [
                    ['name' => 'Instalação de tomadas e interruptores', 'description' => 'Instalação ou troca de tomadas, interruptores e espelhos, com teste de funcionamento.', 'minutes' => 60, 'price' => '120.00', 'type' => PriceType::Fixed],
                    ['name' => 'Instalação de chuveiro elétrico', 'description' => 'Instalação de chuveiro com verificação do disjuntor e da bitola do cabo.', 'minutes' => 60, 'price' => '150.00', 'type' => PriceType::Fixed],
                    ['name' => 'Troca de quadro de distribuição', 'description' => 'Substituição do quadro de disjuntores com identificação dos circuitos.', 'minutes' => 120, 'price' => '850.00', 'type' => PriceType::StartingAt],
                ],
                'Manutenção' => [
                    ['name' => 'Revisão elétrica residencial', 'description' => 'Inspeção completa da instalação, com relatório de pontos de risco.', 'minutes' => 120, 'price' => '280.00', 'type' => PriceType::Fixed],
                    ['name' => 'Reparo de curto-circuito', 'description' => 'Localização e reparo de curto-circuito ou disjuntor que desarma.', 'minutes' => 90, 'price' => null, 'type' => PriceType::OnRequest],
                ],
            ],
            'professionals' => ['Carlos Mendes (demo)', 'Juliano Rocha (demo)'],
            'customers' => ['Ana Beatriz Costa', 'Bruno Fagundes', 'Camila Teixeira', 'Diego Ramos', 'Eduarda Pires', 'Fernando Quadros', 'Gabriela Neves', 'Henrique Souto'],
        ],
        [
            'name' => 'ClimaSul Refrigeração',
            'slug' => 'climasul',
            'segment' => 'climatizacao',
            'city' => 'Caxias do Sul',
            'address' => 'Rua Demonstração, 200 · Centro',
            'phone' => '(54) 3000-0202',
            'whatsapp' => '(54) 90000-0202',
            'owner' => ['name' => 'Marcos Bertolini', 'email' => 'owner@climasul.example.test'],
            'operator' => ['name' => 'Luana Zanella', 'email' => 'operador@climasul.example.test'],
            'accent' => '#0284C7',
            'headline' => 'Ar-condicionado instalado e conservado do jeito certo',
            'about' => 'Empresa fictícia de demonstração. Instalação, higienização e manutenção de ar-condicionado split e janela para casas e escritórios na Serra Gaúcha.',
            'differentials' => ['Técnicos certificados', 'Higienização com bactericida', 'Atendimento em até 48 horas'],
            'region' => 'Caxias do Sul, Farroupilha e Flores da Cunha',
            'categories' => [
                'Instalação' => [
                    ['name' => 'Instalação de split até 12.000 BTUs', 'description' => 'Instalação completa com até 3 metros de tubulação e suporte.', 'minutes' => 120, 'price' => '650.00', 'type' => PriceType::StartingAt],
                    ['name' => 'Desinstalação de ar-condicionado', 'description' => 'Retirada do equipamento com recolhimento do gás.', 'minutes' => 60, 'price' => '250.00', 'type' => PriceType::Fixed],
                ],
                'Manutenção' => [
                    ['name' => 'Higienização de split', 'description' => 'Limpeza das unidades interna e externa, filtros e bandeja.', 'minutes' => 90, 'price' => '220.00', 'type' => PriceType::Fixed],
                    ['name' => 'Recarga de gás', 'description' => 'Verificação de vazamento e recarga do fluido refrigerante.', 'minutes' => 60, 'price' => '300.00', 'type' => PriceType::StartingAt],
                    ['name' => 'Manutenção corretiva', 'description' => 'Diagnóstico e reparo de defeitos, com orçamento antes da troca de peças.', 'minutes' => 90, 'price' => null, 'type' => PriceType::OnRequest],
                ],
            ],
            'professionals' => ['Rafael Boff (demo)', 'Tiago Sartori (demo)'],
            'customers' => ['Alice Tomazoni', 'Bernardo Curra', 'Cecília Pozza', 'Daniel Gasparin', 'Elisa Molon', 'Felipe Andreazza', 'Giovana Rech', 'Igor Bertuol'],
        ],
        [
            'name' => 'AutoTech Mecânica',
            'slug' => 'autotech',
            'segment' => 'oficinas',
            'city' => 'Santa Maria',
            'address' => 'Rua Demonstração, 300 · Bairro Exemplo',
            'phone' => '(55) 3000-0303',
            'whatsapp' => '(55) 90000-0303',
            'owner' => ['name' => 'Sérgio Weber', 'email' => 'owner@autotech.example.test'],
            'operator' => ['name' => 'Renata Flores', 'email' => 'operador@autotech.example.test'],
            'accent' => '#DC2626',
            'headline' => 'Manutenção automotiva transparente, com orçamento antes do serviço',
            'about' => 'Empresa fictícia de demonstração. Oficina mecânica para carros de passeio, com revisão, freios, suspensão e diagnóstico eletrônico em Santa Maria.',
            'differentials' => ['Diagnóstico eletrônico', 'Peças com nota fiscal', 'Garantia de 6 meses'],
            'region' => 'Santa Maria e Itaara',
            'categories' => [
                'Revisão' => [
                    ['name' => 'Troca de óleo e filtros', 'description' => 'Troca de óleo do motor, filtro de óleo e filtro de ar.', 'minutes' => 60, 'price' => '220.00', 'type' => PriceType::StartingAt],
                    ['name' => 'Revisão preventiva completa', 'description' => 'Checagem de 40 itens, fluidos, correias e freios.', 'minutes' => 120, 'price' => '480.00', 'type' => PriceType::Fixed],
                ],
                'Reparos' => [
                    ['name' => 'Troca de pastilhas de freio', 'description' => 'Substituição das pastilhas dianteiras com verificação dos discos.', 'minutes' => 90, 'price' => '320.00', 'type' => PriceType::StartingAt],
                    ['name' => 'Alinhamento e balanceamento', 'description' => 'Alinhamento da direção e balanceamento das quatro rodas.', 'minutes' => 60, 'price' => '160.00', 'type' => PriceType::Fixed],
                    ['name' => 'Diagnóstico eletrônico', 'description' => 'Leitura de falhas com scanner e relatório para o cliente.', 'minutes' => 60, 'price' => '150.00', 'type' => PriceType::Fixed],
                    ['name' => 'Reparo de suspensão', 'description' => 'Avaliação e troca de amortecedores, buchas e pivôs.', 'minutes' => 120, 'price' => null, 'type' => PriceType::OnRequest],
                ],
            ],
            'professionals' => ['Paulo Schmitt (demo)', 'Vinícius Prado (demo)'],
            'customers' => ['André Londero', 'Bianca Dalcin', 'Cristiano Rossato', 'Débora Pippi', 'Everton Zago', 'Franciele Brum', 'Gustavo Lenz', 'Helena Pivetta'],
        ],
    ];

    /**
     * Completed services: days ago, hour, origin, service index, professional, customer,
     * responsible ("owner", "operator" or none) and whether the visit was rescheduled once.
     *
     * @var list<array{days: int, hour: int, origin: RequestOrigin, service: int, professional: int, customer: int, assignee: string|null, reschedule: bool}>
     */
    private const array COMPLETED = [
        ['days' => 56, 'hour' => 9, 'origin' => RequestOrigin::Portal, 'service' => 0, 'professional' => 0, 'customer' => 0, 'assignee' => 'operator', 'reschedule' => false],
        ['days' => 47, 'hour' => 14, 'origin' => RequestOrigin::PublicSite, 'service' => 1, 'professional' => 1, 'customer' => 1, 'assignee' => 'owner', 'reschedule' => true],
        ['days' => 38, 'hour' => 10, 'origin' => RequestOrigin::Ai, 'service' => 2, 'professional' => 0, 'customer' => 2, 'assignee' => 'operator', 'reschedule' => false],
        ['days' => 27, 'hour' => 15, 'origin' => RequestOrigin::Panel, 'service' => 3, 'professional' => 1, 'customer' => 3, 'assignee' => null, 'reschedule' => false],
        ['days' => 18, 'hour' => 8, 'origin' => RequestOrigin::Portal, 'service' => 4, 'professional' => 0, 'customer' => 4, 'assignee' => 'operator', 'reschedule' => false],
        ['days' => 6, 'hour' => 13, 'origin' => RequestOrigin::PublicSite, 'service' => 0, 'professional' => 1, 'customer' => 5, 'assignee' => null, 'reschedule' => false],
    ];

    /**
     * Declared amounts per company (cents), by completed service above. Two of six stay unknown on purpose.
     *
     * @var array<string, list<int|null>>
     */
    private const array AMOUNTS = [
        'silva-eletrica' => [12000, 15000, 92000, null, 34500, null],
        'climasul' => [65000, 25000, 22000, null, 38000, null],
        'autotech' => [24500, 48000, 36000, null, 15000, null],
    ];

    /**
     * Counters of what was created, by company, for the summary.
     *
     * @var array<string, array<string, int>>
     */
    public array $created = [];

    private CarbonImmutable $realNow;

    private CarbonImmutable $today;

    public function __construct(
        private ServiceRequestService $requests,
        private AppointmentScheduler $scheduler,
        private WebsiteService $websites,
        private TenantSubdomainService $subdomains,
        private AssistantConversations $conversations,
        private AuditLogger $auditLogger,
    ) {}

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('VetorSmartDemoSeeder roda somente em ambiente local ou testing. Execução recusada.');
        }

        $password = config('platform.demo_password');

        if (! is_string($password) || mb_strlen($password) < 12) {
            throw new RuntimeException('Defina VETORSMART_DEMO_PASSWORD no .env local (mínimo de 12 caracteres) antes de criar a base de demonstração.');
        }

        // Nothing leaves the process: no e-mail, notification channel or HTTP call. Panel notices are internal records.
        Notification::fake();
        Mail::fake();
        Http::preventStrayRequests();

        $this->realNow = CarbonImmutable::now();
        $this->today = $this->realNow->setTimezone(self::TIMEZONE)->startOfDay();

        try {
            foreach (self::COMPANIES as $company) {
                $this->company($company, $password);
            }
        } finally {
            Date::setTestNow();
        }
    }

    /**
     * @param  array<string, mixed>  $company
     */
    private function company(array $company, string $password): void
    {
        $existing = Tenant::query()->where('slug', $company['slug'])->first();
        $emails = [$company['owner']['email'], $company['operator']['email']];

        if ($existing !== null) {
            if (TenantSetting::query()->forTenant($existing)->first()?->settings['demo_seed'] ?? null) {
                $this->refreshDemoUsers($existing, [$company['owner']['email'] => $company['owner']['name'], $company['operator']['email'] => $company['operator']['name']], $password);
                $this->say("{$company['name']}: já existe na base de demonstração; nada duplicado (nome e senha dos usuários de demonstração atualizados).");
            } else {
                $this->say("{$company['name']}: o slug {$company['slug']} pertence a uma empresa que não é de demonstração; ignorada sem alterações.", 'warn');
            }

            return;
        }

        if (User::query()->whereIn('email', $emails)->exists()) {
            $this->say("{$company['name']}: um dos e-mails de demonstração já pertence a outro usuário; ignorada sem alterações.", 'warn');

            return;
        }

        $segment = Segment::query()->where('slug', $company['segment'])->where('active', true)->first();

        if ($segment === null) {
            $this->say("{$company['name']}: segmento {$company['segment']} inexistente ou inativo (rode o SegmentSeeder); ignorada.", 'warn');

            return;
        }

        $this->created[$company['slug']] = [];

        DB::transaction(function () use ($company, $password, $segment): void {
            $tenant = $this->at($this->today->subDays(70)->setTime(9, 0), fn (): Tenant => $this->setUp($company, $password, $segment));
            $this->operations($tenant, $company);
        });

        $this->say("{$company['name']}: criada.");
    }

    /**
     * Company, users, settings, catalog, team, opening hours and the published site, as they were 70 days ago.
     *
     * @param  array<string, mixed>  $company
     */
    private function setUp(array $company, string $password, Segment $segment): Tenant
    {
        $owner = $this->user($company['owner'], $password);
        $operator = $this->user($company['operator'], $password);

        $tenant = Tenant::create([
            'uuid' => (string) Str::uuid(),
            'name' => $company['name'],
            'slug' => $company['slug'],
            'email' => 'contato@'.$company['slug'].'.example.test',
            'phone' => $company['phone'],
            'segment_id' => $segment->getKey(),
            'status' => TenantStatus::Active,
            'timezone' => self::TIMEZONE,
        ]);
        $this->count($company['slug'], 'empresas');

        foreach ([[$owner, TenantRole::Owner], [$operator, TenantRole::Operator]] as [$user, $role]) {
            TenantUser::create(['tenant_id' => $tenant->getKey(), 'user_id' => $user->getKey(), 'role' => $role, 'status' => MembershipStatus::Active]);
            $this->count($company['slug'], 'usuarios');
        }

        $weekday = ['open' => '08:00', 'close' => '18:00', 'breaks' => [['start' => '12:00', 'end' => '13:00']]];

        TenantSetting::create([
            'tenant_id' => $tenant->getKey(),
            'business_name' => $company['name'],
            'description' => $company['about'],
            'whatsapp' => $company['whatsapp'],
            'address' => $company['address'],
            'city' => $company['city'],
            'state' => 'RS',
            'locale' => 'pt_BR',
            'service_modes' => ['customer_location', 'in_person'],
            'confirmation_policy' => ConfirmationPolicy::Manual,
            'business_hours' => ['0' => null, '1' => $weekday, '2' => $weekday, '3' => $weekday, '4' => $weekday, '5' => $weekday, '6' => null],
            'brand_color' => $company['accent'],
            'settings' => ['demo_seed' => self::MARKER],
        ]);

        $this->subdomains->assign($tenant);
        $this->auditLogger->record('tenant.created', $tenant, $tenant, ['segment_id' => $segment->getKey(), 'demo_seed' => self::MARKER], $owner);

        AssistantSetting::create([
            'tenant_id' => $tenant->getKey(),
            'enabled' => false,
            'service_region' => $company['region'],
            'business_hours_text' => 'Segunda a sexta, das 8h às 12h e das 13h às 18h.',
        ]);

        $services = [];
        $order = 0;

        foreach ($company['categories'] as $categoryName => $items) {
            $category = ServiceCategory::create(['tenant_id' => $tenant->getKey(), 'name' => $categoryName, 'sort_order' => $order, 'active' => true]);

            foreach ($items as $item) {
                $services[] = Service::create([
                    'tenant_id' => $tenant->getKey(),
                    'service_category_id' => $category->getKey(),
                    'name' => $item['name'],
                    'description' => $item['description'],
                    'duration_minutes' => $item['minutes'],
                    'price' => $item['price'],
                    'price_type' => $item['type'],
                    'requires_evaluation' => $item['type'] === PriceType::OnRequest,
                    'active' => true,
                    'sort_order' => $order++,
                ]);
                $this->count($company['slug'], 'servicos');
            }
        }

        $professionals = [];

        foreach ($company['professionals'] as $index => $name) {
            $professional = Professional::create([
                'tenant_id' => $tenant->getKey(),
                'name' => $name,
                'email' => 'profissional'.($index + 1).'@'.$company['slug'].'.example.test',
                'active' => true,
            ]);

            foreach ($services as $service) {
                $professional->services()->attach($service->getKey(), ['tenant_id' => $tenant->getKey()]);
            }

            foreach ([1, 2, 3, 4, 5] as $day) {
                foreach ([['08:00:00', '12:00:00'], ['13:00:00', '18:00:00']] as [$start, $end]) {
                    ProfessionalAvailability::create(['tenant_id' => $tenant->getKey(), 'professional_id' => $professional->getKey(), 'weekday' => $day, 'start_time' => $start, 'end_time' => $end]);
                }
            }

            $professionals[] = $professional;
            $this->count($company['slug'], 'profissionais');
        }

        foreach ($company['customers'] as $index => $name) {
            Customer::create([
                'tenant_id' => $tenant->getKey(),
                'name' => $name,
                'email' => Str::slug($name, '.').'@cliente.example.test',
                'whatsapp' => sprintf('(%s) 90000-%04d', substr($company['phone'], 1, 2), 1000 + $index),
                'city' => $company['city'],
                'state' => 'RS',
            ]);
            $this->count($company['slug'], 'clientes');
        }

        $this->websites->saveDraft($tenant, [
            'headline' => $company['headline'],
            'about' => $company['about'],
            'differentials' => $company['differentials'],
            'accent_color' => $company['accent'],
            'service_ids' => array_map(fn (Service $service): int => (int) $service->getKey(), $services),
            'professional_ids' => array_map(fn (Professional $professional): int => (int) $professional->getKey(), $professionals),
            'show_professionals' => true,
            'show_hours' => true,
            'show_prices' => true,
            'seo' => ['title' => $company['name'].' · '.$company['city'].'/RS', 'description' => Str::limit($company['about'], 155, ''), 'indexable' => true],
        ], $owner);
        $this->websites->publish($tenant, $owner);
        $this->websites->setPortalListing($tenant, true, $owner);
        $this->count($company['slug'], 'sites_publicados');

        return $tenant;
    }

    /**
     * Requests and appointments of the last 60 days and of the next 30, through the real workflow.
     *
     * @param  array<string, mixed>  $company
     */
    private function operations(Tenant $tenant, array $company): void
    {
        $slug = $company['slug'];
        $owner = User::query()->where('email', $company['owner']['email'])->firstOrFail();
        $operator = User::query()->where('email', $company['operator']['email'])->firstOrFail();
        $services = Service::query()->forTenant($tenant)->orderBy('sort_order')->get()->values();
        $professionals = Professional::query()->forTenant($tenant)->orderBy('id')->get()->values();
        $customers = Customer::query()->forTenant($tenant)->orderBy('id')->get()->values();
        $people = ['owner' => $owner, 'operator' => $operator];

        // Completed services, with the declared amount (or none) informed at completion.
        foreach (self::COMPLETED as $index => $plan) {
            $start = $this->pastWeekday($plan['days'])->setTime($plan['hour'], 0);
            $request = $this->booked($tenant, $plan['origin'], $customers[$plan['customer']], $services[$plan['service']], $professionals[$plan['professional']], $start->subDays(3)->setTime(10, 0), $start, $owner, $operator, $plan['assignee'] === null ? null : $people[$plan['assignee']]);

            if ($plan['reschedule']) {
                $moved = $start->addHours(1);
                $this->at($start->subDays(1)->setTime(9, 0), fn () => $this->scheduler->reschedule($request->appointments()->sole(), $moved->utc(), $operator));
                $start = $moved;
                $this->count($slug, 'remarcacoes');
            }

            $this->at($start, fn () => $this->requests->start($request, $operator));
            $this->at($start->addMinutes((int) $services[$plan['service']]->duration_minutes), fn () => $this->requests->complete($request, $operator, self::AMOUNTS[$slug][$index]));
            $this->count($slug, self::AMOUNTS[$slug][$index] === null ? 'concluidos_sem_valor' : 'concluidos_com_valor');
        }

        // Cancelled: one before triage, one after it was booked.
        $early = $this->at($this->today->subDays(41)->setTime(16, 0), fn (): ServiceRequest => $this->requests->create($tenant, ['customer_id' => $customers[6]->getKey(), 'description' => 'Pedido de orçamento (demonstração).'], null, RequestOrigin::Portal));
        $this->at($this->today->subDays(40)->setTime(9, 30), fn () => $this->requests->cancel($early, 'Cliente desistiu do serviço.', $operator));

        $start = $this->pastWeekday(12)->setTime(10, 0);
        $late = $this->booked($tenant, RequestOrigin::PublicSite, $customers[7], $services[1], $professionals[0], $start->subDays(4)->setTime(11, 0), $start, $owner, $operator, $operator);
        $this->at($start->subDays(1)->setTime(17, 0), fn () => $this->requests->cancel($late, 'Cliente pediu para cancelar e vai remarcar no próximo mês.', $operator));
        $this->count($slug, 'cancelados', 2);

        // In progress: started and not concluded yet.
        foreach ([[1, 9, 0, 0, RequestOrigin::Portal], [2, 14, 1, 1, RequestOrigin::Ai]] as [$days, $hour, $professional, $customer, $origin]) {
            $start = $this->pastWeekday($days)->setTime($hour, 0);
            $request = $this->booked($tenant, $origin, $customers[$customer], $services[3], $professionals[$professional], $start->subDays(2)->setTime(15, 0), $start, $owner, $operator, $operator);
            $this->at($start, fn () => $this->requests->start($request, $operator));
            $this->count($slug, 'em_andamento');
        }

        // Future appointments; the second one was moved to another day.
        foreach ([[3, 9, 0, 2, RequestOrigin::Portal, 2], [11, 14, 1, 3, RequestOrigin::PublicSite, 0], [24, 10, 0, 4, RequestOrigin::Panel, 1]] as $position => [$days, $hour, $professional, $customer, $origin, $service]) {
            $start = $this->futureWeekday($days)->setTime($hour, 0);
            $created = $this->realNow->subDays(2 + $position);
            $request = $this->booked($tenant, $origin, $customers[$customer], $services[$service], $professionals[$professional], $created, $start, $owner, $operator, $position === 0 ? $owner : null);

            if ($position === 1) {
                $moved = $this->futureWeekday($days + 2)->setTime($hour, 0);
                $this->at($created->addHours(4), fn () => $this->scheduler->reschedule($request->appointments()->sole(), $moved->utc(), $operator));
                $this->count($slug, 'remarcacoes');
            }

            $this->count($slug, 'agendamentos_futuros');
        }

        // A blocked afternoon (training) in the agenda.
        $block = $this->futureWeekday(5)->setTime(13, 0);
        $this->at($this->realNow->subDays(1), fn () => $this->scheduler->block($tenant, $professionals[1], $block->utc(), $block->setTime(18, 0)->utc(), 'Treinamento técnico (demonstração)', $owner));
        $this->count($slug, 'bloqueios');

        // Waiting for triage.
        foreach ([[26, RequestOrigin::Portal, 5], [5, RequestOrigin::PublicSite, 6]] as [$hours, $origin, $customer]) {
            $this->at($this->realNow->subHours($hours), fn () => $this->requests->create($tenant, ['customer_id' => $customers[$customer]->getKey(), 'description' => 'Gostaria de um orçamento para o serviço (demonstração).'], null, $origin));
            $this->count($slug, 'aguardando_triagem');
        }

        // A conversation of the assistant handed to a human, waiting for the team.
        $this->at($this->realNow->subHours(2), function () use ($tenant): void {
            $setting = AssistantSetting::query()->forTenant($tenant)->firstOrFail();
            $conversation = $this->conversations->start($tenant, $setting, null)['conversation'];
            $this->conversations->appendMessage($conversation, AssistantMessageRole::Visitor, 'Olá! Preciso de atendimento ainda hoje, é possível? (demonstração)');
            $this->conversations->appendMessage($conversation, AssistantMessageRole::Assistant, 'Vou chamar uma pessoa da equipe para confirmar a disponibilidade de hoje.');
            $this->conversations->handoff($conversation, 'Cliente pediu atendimento no mesmo dia.');
        });
        $this->count($slug, 'aguardando_atendimento_humano');
    }

    /**
     * A request received at $createdAt, triaged and assigned shortly after, and booked for $start.
     */
    private function booked(Tenant $tenant, RequestOrigin $origin, Customer $customer, Service $service, Professional $professional, CarbonImmutable $createdAt, CarbonImmutable $start, User $owner, User $operator, ?User $assignee): ServiceRequest
    {
        $request = $this->at($createdAt, fn (): ServiceRequest => $this->requests->create($tenant, [
            'customer_id' => $customer->getKey(),
            'description' => 'Solicitação de '.Str::lower($service->name).' (demonstração).',
        ], $origin === RequestOrigin::Panel ? $operator : null, $origin));

        $this->at($createdAt->addMinutes(40), fn () => $this->requests->triage($request, ['service_id' => $service->getKey(), 'priority' => RequestPriority::Normal->value], $operator));

        if ($assignee !== null) {
            $this->at($createdAt->addMinutes(50), fn () => $this->requests->assign($request, $assignee, $owner));
        }

        $this->at($createdAt->addHour(), fn () => $this->scheduler->schedule($request, $professional, $start->utc(), $operator));

        return $request->refresh();
    }

    /**
     * Run a step with the clock at that moment, so its records carry the right dates.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $step
     * @return TResult
     */
    private function at(CarbonImmutable $moment, Closure $step): mixed
    {
        Date::setTestNow($moment->utc());

        try {
            return $step();
        } finally {
            Date::setTestNow();
        }
    }

    /**
     * A weekday (Monday to Friday) $days before today, moving back over the weekend.
     */
    private function pastWeekday(int $days): CarbonImmutable
    {
        $day = $this->today->subDays($days);

        while ($day->isWeekend()) {
            $day = $day->subDay();
        }

        return $day;
    }

    /**
     * A weekday (Monday to Friday) $days after today, moving forward over the weekend.
     */
    private function futureWeekday(int $days): CarbonImmutable
    {
        $day = $this->today->addDays($days);

        while ($day->isWeekend()) {
            $day = $day->addDay();
        }

        return $day;
    }

    /**
     * @param  array{name: string, email: string}  $person
     */
    private function user(array $person, string $password): User
    {
        $user = User::create(['name' => $person['name'], 'email' => $person['email'], 'password' => $password, 'status' => UserStatus::Active]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /**
     * Refresh the password and the name of the demo users of a demo company only (never of anyone else).
     *
     * @param  array<string, string>  $names  name by e-mail
     */
    private function refreshDemoUsers(Tenant $tenant, array $names, string $password): void
    {
        User::query()
            ->whereIn('email', array_keys($names))
            ->where('email', 'like', '%.example.test')
            ->whereHas('memberships', fn ($query) => $query->where('tenant_id', $tenant->getKey()))
            ->get()
            ->each(fn (User $user) => $user->forceFill(['name' => $names[$user->email], 'password' => $password])->save());
    }

    private function count(string $slug, string $entity, int $amount = 1): void
    {
        $this->created[$slug][$entity] = ($this->created[$slug][$entity] ?? 0) + $amount;
    }

    private function say(string $message, string $level = 'info'): void
    {
        if ($this->command !== null) {
            $level === 'warn' ? $this->command->warn($message) : $this->command->info($message);
        }
    }
}
