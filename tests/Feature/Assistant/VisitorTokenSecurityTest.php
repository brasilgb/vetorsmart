<?php

use App\Enums\AssistantState;
use App\Enums\ConversationStatus;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use Illuminate\Support\Str;
use Tests\Support\VisitorSite;
use Tests\TestCase;

/*
 * Visitor tokens over HTTP. A token is a random secret sent in an encrypted cookie; the server keeps only its hash and
 * always looks it up inside the company that answers the request. No token may open another company's conversation,
 * another visitor's conversation, or a conversation past its retention period.
 */

beforeEach(function () {
    $this->companyA = VisitorSite::publish($this, 'Clima Total', 'clima-total');
    $this->companyB = VisitorSite::publish($this, 'Pet Center', 'pet-center');
});

/**
 * A visitor of company A who has written one message, and that message's text.
 *
 * @return array{token: string, conversation: AssistantConversation, text: string}
 */
function visitorOfA(TestCase $test, string $text = 'Segredo do visitor A: limpeza no sábado'): array
{
    $start = VisitorSite::start($test, $test->companyA['site']);
    VisitorSite::say($test, $test->companyA['site'], $start['token'], $text);

    return [
        'token' => $start['token'],
        'conversation' => AssistantConversation::query()->where('tenant_id', $test->companyA['tenant']->id)->sole(),
        'text' => $text,
    ];
}

test('a token issued in company A reads nothing in company B', function () {
    $visitor = visitorOfA($this);

    $response = VisitorSite::read($this, $this->companyB['site'], $visitor['token']);

    expect($response->status())->toBeIn([200, 404])
        ->and($response->getContent())->not->toContain('Segredo do visitor A');
});

test('a token issued in company A writes nothing in company B', function () {
    $visitor = visitorOfA($this);
    $messagesOfA = AssistantMessage::query()->where('assistant_conversation_id', $visitor['conversation']->getKey())->count();

    VisitorSite::say($this, $this->companyB['site'], $visitor['token'], 'Mensagem enviada à empresa B');

    expect(AssistantConversation::query()->where('tenant_id', $this->companyB['tenant']->id)->count())->toBe(0)
        ->and(AssistantMessage::query()->where('body', 'Mensagem enviada à empresa B')->exists())->toBeFalse()
        ->and(AssistantMessage::query()->where('assistant_conversation_id', $visitor['conversation']->getKey())->count())->toBe($messagesOfA);
});

test('a tampered cookie grants no access to any conversation', function () {
    $visitor = visitorOfA($this);

    $this->withUnencryptedCookie((string) config('assistant.session_cookie'), $visitor['token'])
        ->get('http://localhost/'.$this->companyA['site'].'/atendimento/mensagens?after=0', ['Accept' => 'application/json'])
        ->assertDontSee('Segredo do visitor A');
});

test('an unknown token, correctly encrypted, finds no conversation', function () {
    visitorOfA($this);

    $response = VisitorSite::read($this, $this->companyA['site'], Str::random(48));

    expect($response->getContent())->not->toContain('Segredo do visitor A');
});

test('a missing token finds no conversation and creates no message', function () {
    visitorOfA($this);
    $before = AssistantMessage::query()->count();

    VisitorSite::read($this, $this->companyA['site'], null)->assertDontSee('Segredo do visitor A');
    VisitorSite::say($this, $this->companyA['site'], null, 'Mensagem sem token');

    expect(AssistantMessage::query()->where('body', 'Mensagem sem token')->count())->toBe(0)
        ->and(AssistantMessage::query()->count())->toBe($before);
});

test('an expired conversation cannot be reached by its token', function () {
    $visitor = visitorOfA($this);
    $visitor['conversation']->forceFill(['retention_until' => now()->subDay()])->save();

    $response = VisitorSite::read($this, $this->companyA['site'], $visitor['token']);

    expect($response->getContent())->not->toContain('Segredo do visitor A');
});

test('a visitor cannot read the conversation of another visitor in the same company', function () {
    $first = visitorOfA($this);
    $secondStart = VisitorSite::start($this, $this->companyA['site']);
    VisitorSite::say($this, $this->companyA['site'], $secondStart['token'], 'Mensagem do visitor B');

    expect(AssistantConversation::query()->where('tenant_id', $this->companyA['tenant']->id)->count())->toBe(2)
        ->and(VisitorSite::read($this, $this->companyA['site'], $secondStart['token'])->getContent())->not->toContain('Segredo do visitor A')
        ->and(VisitorSite::read($this, $this->companyA['site'], $first['token'])->getContent())->not->toContain('Mensagem do visitor B');
});

test('identifiers sent in the body are ignored: the token alone selects the conversation', function () {
    $first = visitorOfA($this);
    $second = VisitorSite::start($this, $this->companyA['site']);

    VisitorSite::say($this, $this->companyA['site'], $second['token'], 'Mensagem forjada');
    $this->withCookie((string) config('assistant.session_cookie'), $second['token'])
        ->post('http://localhost/'.$this->companyA['site'].'/atendimento/mensagens', [
            'body' => 'Tentativa com outros identificadores',
            'after' => 0,
            'conversation_id' => $first['conversation']->getKey(),
            'tenant_id' => $this->companyB['tenant']->id,
            'session_hash' => hash('sha256', $first['token']),
        ], ['Accept' => 'application/json'])
        ->assertOk();

    expect(AssistantMessage::query()->where('assistant_conversation_id', $first['conversation']->getKey())->where('body', 'Tentativa com outros identificadores')->exists())->toBeFalse()
        ->and(AssistantMessage::query()->where('body', 'Tentativa com outros identificadores')->value('tenant_id'))->toBe($this->companyA['tenant']->id);
});

test('an array cookie is ignored, not passed on as a token', function () {
    $tenant = $this->companyA['tenant'];
    $conversation = AssistantConversation::create([
        'tenant_id' => $tenant->getKey(),
        'channel' => 'site',
        'session_hash' => hash('sha256', 'token-conhecido'),
        'state' => AssistantState::Greeting,
        'status' => ConversationStatus::Active,
        'collected' => [],
        'last_activity_at' => now(),
        'retention_until' => now()->addDays(30),
    ]);
    AssistantMessage::create(['tenant_id' => $tenant->getKey(), 'assistant_conversation_id' => $conversation->getKey(), 'role' => 'visitor', 'body' => 'Segredo do visitor A: via array', 'created_at' => now(), 'updated_at' => now()]);

    // Nothing else is sent: the only cookie is the array form of the visitor cookie.
    $response = $this->call('GET', 'http://localhost/'.$this->companyA['site'].'/atendimento/mensagens?after=0', [], [(string) config('assistant.session_cookie') => ['token-conhecido']], [], ['HTTP_ACCEPT' => 'application/json']);

    expect($response->status())->toBeLessThan(500)
        ->and($response->getContent())->not->toContain('Segredo do visitor A');
});

test('a closed conversation accepts no further visitor messages', function () {
    $visitor = visitorOfA($this);
    $visitor['conversation']->forceFill(['status' => ConversationStatus::Closed])->save();
    $before = AssistantMessage::query()->where('assistant_conversation_id', $visitor['conversation']->getKey())->count();

    VisitorSite::say($this, $this->companyA['site'], $visitor['token'], 'Depois de encerrada');

    expect(AssistantMessage::query()->where('assistant_conversation_id', $visitor['conversation']->getKey())->count())->toBe($before)
        ->and(AssistantMessage::query()->where('body', 'Depois de encerrada')->exists())->toBeFalse();
});

test('an expired conversation is not reopened by its token: the visitor gets a new conversation', function () {
    $visitor = visitorOfA($this);
    $visitor['conversation']->forceFill(['retention_until' => now()->subDay()])->save();
    $messages = AssistantMessage::query()->where('assistant_conversation_id', $visitor['conversation']->getKey())->count();

    $restart = VisitorSite::start($this, $this->companyA['site']);

    expect($restart['token'])->not->toBe($visitor['token'])
        ->and(AssistantConversation::query()->where('tenant_id', $this->companyA['tenant']->id)->count())->toBe(2)
        ->and(AssistantMessage::query()->where('assistant_conversation_id', $visitor['conversation']->getKey())->count())->toBe($messages);
});
