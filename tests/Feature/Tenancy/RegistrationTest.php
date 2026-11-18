<?php

use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use App\Services\AuditLogger;
use App\Services\TenantRegistrationService;
use Database\Seeders\SegmentSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Mockery\MockInterface;

beforeEach(function () {
    $this->seed(SegmentSeeder::class);

    $this->segment = Segment::query()->where('slug', 'limpeza')->firstOrFail();
});

/**
 * @return array<string, string|int>
 */
function registrationPayload(array $overrides = []): array
{
    return array_merge([
        'company_name' => 'Clean Pro Ltda',
        'segment_id' => test()->segment->id,
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], $overrides);
}

test('a company and its owner are created and the owner is signed in', function () {
    $response = $this->post(route('register.store'), registrationPayload());

    $response->assertRedirect(route('dashboard', absolute: false));
    $this->assertAuthenticated();

    $tenant = Tenant::query()->where('name', 'Clean Pro Ltda')->firstOrFail();

    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and($tenant->segment_id)->toBe($this->segment->id)
        ->and($tenant->slug)->toBe('clean-pro-ltda');

    $membership = TenantUser::query()->where('tenant_id', $tenant->id)->firstOrFail();

    expect($membership->role)->toBe(TenantRole::Owner)
        ->and($membership->user->email)->toBe('ana@example.com');

    expect(TenantSetting::query()->where('tenant_id', $tenant->id)->value('business_name'))
        ->toBe('Clean Pro Ltda');
});

test('registration sends the confirmation link and keeps the panel closed until the e-mail is verified', function () {
    Notification::fake();

    $this->post(route('register.store'), registrationPayload());

    $user = User::query()->where('email', 'ana@example.com')->firstOrFail();

    expect($user->email_verified_at)->toBeNull();
    Notification::assertSentTo($user, VerifyEmailNotification::class);

    $this->get(route('dashboard'))->assertRedirect(route('verification.notice'));
    $this->get(route('tenant.customers.index'))->assertRedirect(route('verification.notice'));
    $this->get(route('verification.notice'))->assertOk();
});

test('the confirmation link opens the panel', function () {
    $this->post(route('register.store'), registrationPayload());

    $user = User::query()->where('email', 'ana@example.com')->firstOrFail();
    $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $user->id, 'hash' => sha1($user->email)]);

    $this->get($link)->assertRedirect();

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->get(route('dashboard'))->assertOk();
});

test('a confirmation link for another account is refused', function () {
    $this->post(route('register.store'), registrationPayload());

    $user = User::query()->where('email', 'ana@example.com')->firstOrFail();
    $other = User::factory()->unverified()->create();
    $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $other->id, 'hash' => sha1($other->email)]);

    $this->get($link)->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse()
        ->and($other->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('an unverified account cannot use the tenant API', function () {
    $this->post(route('register.store'), registrationPayload());

    $this->getJson('/api/v1/tenant/customers')->assertForbidden();
});

test('registration refuses the reserved homologation prefix', function (string $name) {
    $response = $this->post(route('register.store'), registrationPayload(['company_name' => $name]));

    $response->assertSessionHasErrors(['company_name' => 'Este nome é reservado. Escolha outro nome para a empresa.']);
    expect(Tenant::query()->count())->toBe(0);
})->with(['[HOMOLOG] Empresa', '[homolog] empresa', '  [ Homolog-SRV ] Empresa']);

test('a name that only mentions homologation elsewhere is accepted', function () {
    $this->post(route('register.store'), registrationPayload(['company_name' => 'Homologação Técnica Ltda']))
        ->assertSessionHasNoErrors();

    expect(Tenant::query()->value('is_homolog'))->toBeFalse();
});

test('a filled hidden field marks the submission as automated and creates nothing', function () {
    $response = $this->post(route('register.store'), registrationPayload(['website' => 'https://spam.example']));

    $response->assertSessionHasErrors(['email' => 'Não foi possível concluir o cadastro. Tente novamente.']);
    expect(Tenant::query()->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});

test('a burst of registrations from one network is limited per minute', function () {
    RateLimiter::increment('register:127.0.0.1', 60, 5);

    $response = $this->post(route('register.store'), registrationPayload());

    $response->assertSessionHasErrors(['email' => 'Muitas tentativas de cadastro. Aguarde um minuto e tente novamente.']);
    expect(Tenant::query()->count())->toBe(0);
});

test('one network is limited per hour even when the attempts are spread over minutes', function () {
    RateLimiter::increment('register-hour:127.0.0.1', 3600, 20);

    $response = $this->post(route('register.store'), registrationPayload());

    $response->assertSessionHasErrors(['email' => 'Muitas tentativas de cadastro a partir desta rede. Tente novamente mais tarde.']);
    expect(Tenant::query()->count())->toBe(0);
});

test('registration rejects an inactive segment', function () {
    $inactive = Segment::factory()->inactive()->create();

    $response = $this->post(route('register.store'), registrationPayload(['segment_id' => $inactive->id]));

    $response->assertSessionHasErrors('segment_id');
    expect(Tenant::query()->count())->toBe(0);
});

test('public registration cannot assign a privileged account or root admin flag', function () {
    $this->post(route('register.store'), registrationPayload([
        'is_root_admin' => true,
        'role' => 'owner-root',
    ]));

    expect(User::query()->where('email', 'ana@example.com')->value('is_root_admin'))->toBeFalse()
        ->and(TenantUser::query()->value('role'))->toBe(TenantRole::Owner);
});

test('a failed registration leaves no partial records', function () {
    $this->mock(AuditLogger::class, function (MockInterface $mock) {
        $mock->shouldReceive('record')->andThrow(new RuntimeException('falha simulada'));
    });

    expect(fn () => app(TenantRegistrationService::class)->register([
        'company_name' => 'Parcial Ltda',
        'segment_id' => $this->segment->id,
        'name' => 'Beto',
        'email' => 'beto@example.com',
        'password' => 'password',
    ]))->toThrow(RuntimeException::class, 'falha simulada');

    expect(User::query()->where('email', 'beto@example.com')->exists())->toBeFalse()
        ->and(Tenant::query()->where('name', 'Parcial Ltda')->exists())->toBeFalse()
        ->and(TenantUser::query()->count())->toBe(0)
        ->and(TenantSetting::query()->count())->toBe(0);
});

test('repeated registrations from one address are rate limited', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post(route('register.store'), registrationPayload([
            'email' => "tentativa{$attempt}@example.com",
            'company_name' => "Empresa {$attempt}",
        ]));
        auth()->logout();
    }

    $response = $this->post(route('register.store'), registrationPayload([
        'email' => 'bloqueado@example.com',
        'company_name' => 'Bloqueada',
    ]));

    $response->assertSessionHasErrors('email');
    expect(Tenant::query()->where('name', 'Bloqueada')->exists())->toBeFalse();
});

test('a registration still succeeds when a simultaneous one takes the same slug first', function () {
    $raced = false;

    // Another registration commits the same slug between the availability check and this insert.
    Tenant::creating(function (Tenant $tenant) use (&$raced) {
        if (! $raced) {
            $raced = true;
            Tenant::factory()->create(['name' => 'Concorrente', 'slug' => $tenant->slug]);
        }
    });

    $this->post(route('register.store'), registrationPayload())->assertRedirect(route('dashboard', absolute: false));

    $tenant = Tenant::query()->where('name', 'Clean Pro Ltda')->sole();

    // The rival row lives inside the failed savepoint and is rolled back with it; what matters is the retry.
    expect($tenant->slug)->toStartWith('clean-pro-ltda-');
});
