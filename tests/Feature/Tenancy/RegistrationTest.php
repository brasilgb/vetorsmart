<?php

use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\TenantRegistrationService;
use Database\Seeders\SegmentSeeder;
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

test('registration does not require email verification', function () {
    $this->post(route('register.store'), registrationPayload());

    expect(User::query()->where('email', 'ana@example.com')->value('email_verified_at'))->toBeNull();

    $this->get(route('dashboard'))->assertOk();
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
