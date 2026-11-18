<?php

use App\Enums\AssistantMessageRole;
use App\Enums\ConversationStatus;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Assistant\AssistantConversations;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Inertia\Testing\AssertableInertia;
use Tests\Support\VisitorSite;

/*
| RELEASE-10.2: homologation companies are never indexable and are labelled as test data; the Content Security
| Policy starts in report-only mode; conversation retention runs as a simulation; the request summary keeps the
| conversation in the order it happened.
*/

function expiredConversation(Tenant $tenant, string $key, ConversationStatus $status = ConversationStatus::Closed): AssistantConversation
{
    $conversation = AssistantConversation::query()->create([
        'tenant_id' => $tenant->id,
        'channel' => 'site',
        'session_hash' => hash('sha256', $key),
        'state' => 'service',
        'status' => $status,
        'collected' => [],
        'retention_until' => now()->subDay(),
    ]);

    AssistantMessage::query()->create([
        'tenant_id' => $tenant->id,
        'assistant_conversation_id' => $conversation->id,
        'role' => AssistantMessageRole::Visitor->value,
        'body' => 'mensagem antiga',
    ]);

    return $conversation;
}

test('a homologation site is never indexable, stays out of the sitemap and tells the visitor it is a test', function () {
    $homolog = VisitorSite::publish($this, '[HOMOLOG] Empresa de Teste', 'homolog-empresa-de-teste', homolog: true);
    $real = VisitorSite::publish($this, 'Clima Real', 'clima-real');

    $this->get('/'.$homolog['site'])
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('Ambiente de teste da VetorSmart')
        ->assertDontSee('application/ld+json', false);

    $this->get('/'.$homolog['site'].'/atendimento')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('Ambiente de teste da VetorSmart');

    $this->get('/'.$real['site'])
        ->assertOk()
        ->assertHeaderMissing('X-Robots-Tag')
        ->assertDontSee('noindex', false)
        ->assertDontSee('Ambiente de teste');
    $this->get('/'.$real['site'].'/atendimento')->assertOk()->assertDontSee('Ambiente de teste');

    $sitemap = $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($sitemap)->toContain('/climatizacao/clima-real')->not->toContain('homolog');
});

test('the home page shows the company text once', function () {
    $site = VisitorSite::publish($this, 'Clima Real', 'clima-real');

    $this->actingAs($site['owner'])->patch('/meu-site/rascunho', ['section' => 'content', 'headline' => 'Clima Real', 'about' => 'Texto institucional único da empresa.'])->assertRedirect('/meu-site');
    $this->post('/meu-site/publicar')->assertRedirect('/meu-site');
    auth()->logout();

    $html = $this->get('/'.$site['site'])->assertOk()->getContent();
    $body = substr($html, (int) strpos($html, '<body'));

    expect(substr_count($body, 'Texto institucional único da empresa.'))->toBe(1);
});

test('the console labels homologation companies', function () {
    $homolog = Tenant::factory()->homolog()->create(['name' => '[HOMOLOG] Empresa de Teste']);
    $real = Tenant::factory()->create(['name' => 'Empresa Real']);

    $this->actingAs(User::factory()->rootAdmin()->create());

    $this->get('/rootadmin/tenants')->assertInertia(fn (AssertableInertia $page) => $page
        ->where('tenants.data.0.id', $real->id)->where('tenants.data.0.is_homolog', false)
        ->where('tenants.data.1.id', $homolog->id)->where('tenants.data.1.is_homolog', true));

    $this->get("/rootadmin/tenants/{$homolog->id}")->assertInertia(fn (AssertableInertia $page) => $page->where('tenant.is_homolog', true));
    $this->get('/rootadmin')->assertInertia(fn (AssertableInertia $page) => $page->where('recent.0.is_homolog', false)->where('recent.1.is_homolog', true));
});

test('the request summary keeps the last messages in the order they were said', function () {
    $site = VisitorSite::publish($this, 'Clima Real', 'clima-real');
    $conversation = expiredConversation($site['tenant'], 'ordem');
    $conversation->messages()->delete();

    foreach (range(1, 14) as $number) {
        AssistantMessage::query()->create([
            'tenant_id' => $site['tenant']->id,
            'assistant_conversation_id' => $conversation->id,
            'role' => ($number % 2 === 1 ? AssistantMessageRole::Visitor : AssistantMessageRole::Assistant)->value,
            'body' => "fala {$number}",
        ]);
    }

    $summary = app(AssistantConversations::class)->summarize($conversation->fresh());
    preg_match_all('/fala (\d+)/', $summary, $found);

    expect(array_map('intval', $found[1]))->toBe(range(3, 14));
});

test('pages carry a report-only content security policy with a nonce and no unsafe script source', function () {
    $response = $this->get('/login')->assertOk();
    $policy = (string) $response->headers->get('Content-Security-Policy-Report-Only');

    expect($response->headers->has('Content-Security-Policy'))->toBeFalse()
        ->and($policy)->toContain("default-src 'self'")
        ->and($policy)->toContain("object-src 'none'")
        ->and($policy)->toContain("frame-ancestors 'self'")
        ->and($policy)->toContain('report-uri https://vetorsmart.com.br/api/csp-report')
        ->and($policy)->not->toContain('unsafe-eval');

    preg_match('/script-src ([^;]+)/', $policy, $scripts);
    preg_match("/'nonce-([A-Za-z0-9]+)'/", $scripts[1], $nonce);

    expect($scripts[1])->not->toContain('unsafe-inline')
        ->and($nonce[1] ?? '')->not->toBe('')
        ->and($response->getContent())->toContain('<script nonce="'.$nonce[1].'">');

    preg_match("/'nonce-([A-Za-z0-9]+)'/", (string) $this->get('/login')->headers->get('Content-Security-Policy-Report-Only'), $second);

    expect($second[1])->not->toBe($nonce[1]);
});

test('the policy can be turned off or enforced by configuration', function () {
    config(['csp.mode' => 'off']);
    $off = $this->get('/login');

    expect($off->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse()
        ->and($off->headers->has('Content-Security-Policy'))->toBeFalse();

    config(['csp.mode' => 'enforce']);
    $enforced = $this->get('/login');

    expect($enforced->headers->has('Content-Security-Policy'))->toBeTrue()
        ->and($enforced->headers->has('Content-Security-Policy-Report-Only'))->toBeFalse();
});

test('violation reports are logged without query strings and oversized or malformed bodies are ignored', function () {
    Log::spy();

    $report = json_encode(['csp-report' => [
        'effective-directive' => 'script-src-elem',
        'blocked-uri' => 'https://evil.example/x.js?token=segredo',
        'document-uri' => 'https://vetorsmart.com.br/login?email=pessoa@example.com',
        'line-number' => 12,
    ]]);

    $this->call('POST', '/api/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], $report)->assertNoContent();

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message, array $context): bool => $message === 'csp-violation'
        && $context['directive'] === 'script-src-elem'
        && $context['blocked'] === 'https://evil.example/x.js'
        && $context['document'] === 'https://vetorsmart.com.br/login'
        && $context['line'] === 12);

    $this->call('POST', '/api/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], 'não é json')->assertNoContent();
    $this->call('POST', '/api/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], json_encode(['csp-report' => ['blocked-uri' => str_repeat('a', 9000)]]))->assertNoContent();

    Log::shouldHaveReceived('warning')->once();
});

test('the retention routine reports per company and removes nothing by default', function () {
    $first = VisitorSite::publish($this, 'Clima Real', 'clima-real');
    $second = VisitorSite::publish($this, 'Frio Bom', 'frio-bom');

    expiredConversation($first['tenant'], 'a');
    expiredConversation($first['tenant'], 'b', ConversationStatus::AwaitingHuman);
    expiredConversation($second['tenant'], 'c');

    $this->artisan('assistant:purge')
        ->expectsOutputToContain('3 conversa(s) vencida(s) em 2 empresa(s), com 3 mensagem(ns). Nada foi removido.')
        ->assertSuccessful();

    $this->artisan('assistant:purge', ['--tenant' => $second['tenant']->id])
        ->expectsOutputToContain('1 conversa(s) vencida(s) em 1 empresa(s)')
        ->assertSuccessful();

    expect(AssistantConversation::query()->count())->toBe(3)
        ->and(AssistantMessage::query()->count())->toBe(3);
});

test('removal is refused until it is enabled, and then respects the company filter', function () {
    $first = VisitorSite::publish($this, 'Clima Real', 'clima-real');
    $second = VisitorSite::publish($this, 'Frio Bom', 'frio-bom');

    expiredConversation($first['tenant'], 'a');
    expiredConversation($second['tenant'], 'c');

    $this->artisan('assistant:purge', ['--execute' => true])->assertFailed();
    expect(AssistantConversation::query()->count())->toBe(2);

    config(['assistant.retention_deletion_enabled' => true]);

    $this->artisan('assistant:purge', ['--execute' => true, '--tenant' => 999999])->assertFailed();
    $this->artisan('assistant:purge', ['--execute' => true, '--tenant' => $first['tenant']->id])->assertSuccessful();

    expect(AssistantConversation::query()->pluck('tenant_id')->all())->toBe([$second['tenant']->id])
        ->and(AssistantMessage::query()->count())->toBe(1);
});

test('the retention routine is scheduled daily as a simulation', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains((string) $event->command, 'assistant:purge'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('30 6 * * *')
        ->and((string) $events->first()->command)->not->toContain('--execute');
});
