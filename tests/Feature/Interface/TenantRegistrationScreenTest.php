<?php

use App\Enums\TenantRole;
use App\Enums\TenantStatus;
use App\Models\Segment;
use App\Models\Tenant;
use App\Models\TenantUser;
use Database\Seeders\SegmentSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->seed(SegmentSeeder::class);
});

test('the registration screen renders the single-screen form with active segments', function () {
    Segment::factory()->inactive()->create(['name' => 'Inativo Teste']);

    $this->get(route('register'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('auth/register')
            ->has('segments', 10)
            ->missing('segments.10'));
});

test('the login screen renders the universal form without password recovery', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('auth/login'));
});

test('a company can be registered with only the minimum fields', function () {
    $segment = Segment::query()->where('slug', 'limpeza')->firstOrFail();

    $this->post(route('register.store'), [
        'company_name' => 'Brilho Limpeza',
        'segment_id' => $segment->id,
        'name' => 'Marta',
        'email' => 'marta@example.com',
        'password' => 'Senha-Forte-2026',
        'password_confirmation' => 'Senha-Forte-2026',
    ])->assertRedirect(route('dashboard', absolute: false));

    $tenant = Tenant::query()->where('name', 'Brilho Limpeza')->firstOrFail();

    expect($tenant->status)->toBe(TenantStatus::Active)
        ->and(TenantUser::query()->where('tenant_id', $tenant->id)->value('role'))->toBe(TenantRole::Owner);
});

test('an empty or inconsistent registration shows field errors', function () {
    $this->post(route('register.store'), [
        'company_name' => '',
        'segment_id' => 999999,
        'name' => '',
        'email' => 'nao-e-email',
        'password' => 'curta',
        'password_confirmation' => 'diferente',
    ])->assertSessionHasErrors(['company_name', 'segment_id', 'name', 'email', 'password']);

    expect(Tenant::query()->count())->toBe(0);
});

test('two different companies may share the same name and receive distinct addresses', function () {
    $segment = Segment::query()->where('slug', 'limpeza')->firstOrFail();

    $this->post(route('register.store'), [
        'company_name' => 'Clima Total',
        'segment_id' => $segment->id,
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'password' => 'Senha-Forte-2026',
        'password_confirmation' => 'Senha-Forte-2026',
    ]);

    auth()->logout();

    $this->post(route('register.store'), [
        'company_name' => 'Clima Total',
        'segment_id' => $segment->id,
        'name' => 'Beto',
        'email' => 'beto@example.com',
        'password' => 'Senha-Forte-2026',
        'password_confirmation' => 'Senha-Forte-2026',
    ]);

    $slugs = Tenant::query()->where('name', 'Clima Total')->pluck('slug')->all();

    expect($slugs)->toHaveCount(2)
        ->and(array_unique($slugs))->toHaveCount(2);
});
