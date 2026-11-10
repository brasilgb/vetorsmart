<?php

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the command provisions a root admin with a hidden password', function () {
    $this->artisan('platform:root-admin', ['email' => 'root@abrasil.test', '--name' => 'Root Admin'])
        ->expectsQuestion('Senha (mínimo 12 caracteres)', 'senha-muito-segura-123')
        ->expectsOutput('RootAdmin criado.')
        ->assertSuccessful();

    $root = User::query()->where('email', 'root@abrasil.test')->firstOrFail();

    expect($root->is_root_admin)->toBeTrue()
        ->and($root->status)->toBe(UserStatus::Active)
        ->and(Hash::check('senha-muito-segura-123', $root->password))->toBeTrue()
        ->and($root->isRootAdmin())->toBeTrue();
});

test('the command never writes the password to the audit log', function () {
    $this->artisan('platform:root-admin', ['email' => 'root2@abrasil.test', '--name' => 'Root'])
        ->expectsQuestion('Senha (mínimo 12 caracteres)', 'senha-muito-segura-123')
        ->assertSuccessful();

    expect(json_encode(AuditLog::query()->get()->toArray()))->not->toContain('senha-muito-segura-123');
    expect(AuditLog::query()->value('action'))->toBe('platform.root_admin_provisioned');
});

test('the command promotes an existing user without changing the password', function () {
    $user = User::factory()->create(['email' => 'existente@abrasil.test']);
    $passwordBefore = $user->password;

    $this->artisan('platform:root-admin', ['email' => 'existente@abrasil.test'])
        ->assertSuccessful();

    expect($user->fresh()->is_root_admin)->toBeTrue()
        ->and($user->fresh()->password)->toBe($passwordBefore);
});

test('the command rejects a weak password', function () {
    $this->artisan('platform:root-admin', ['email' => 'fraco@abrasil.test', '--name' => 'Fraco'])
        ->expectsQuestion('Senha (mínimo 12 caracteres)', 'curta')
        ->expectsOutput('Nome ou senha inválidos. Informe um nome e uma senha de ao menos 12 caracteres.')
        ->assertFailed();

    expect(User::query()->where('email', 'fraco@abrasil.test')->exists())->toBeFalse();
});

test('the command refuses to run without an interactive terminal', function () {
    $this->artisan('platform:root-admin', ['email' => 'nao@abrasil.test', '--no-interaction' => true])
        ->assertFailed();

    expect(User::query()->where('email', 'nao@abrasil.test')->exists())->toBeFalse();
});

test('the command provisions a root admin without a terminal from an environment variable', function () {
    putenv('TEST_ROOT_ADMIN_PASSWORD=senha-muito-segura-456');

    try {
        $this->artisan('platform:root-admin', [
            'email' => 'ci-root@abrasil.test',
            '--name' => 'Root CI',
            '--password-env' => 'TEST_ROOT_ADMIN_PASSWORD',
            '--no-interaction' => true,
        ])->assertSuccessful();
    } finally {
        putenv('TEST_ROOT_ADMIN_PASSWORD');
    }

    $root = User::query()->where('email', 'ci-root@abrasil.test')->firstOrFail();

    expect($root->is_root_admin)->toBeTrue()
        ->and(Hash::check('senha-muito-segura-456', $root->password))->toBeTrue()
        ->and(json_encode(AuditLog::query()->get()->toArray()))->not->toContain('senha-muito-segura-456');
});

test('an empty or missing environment variable is refused without creating anything', function () {
    putenv('TEST_EMPTY_ROOT_PASSWORD=');

    try {
        $this->artisan('platform:root-admin', [
            'email' => 'vazio@abrasil.test',
            '--name' => 'Vazio',
            '--password-env' => 'TEST_EMPTY_ROOT_PASSWORD',
            '--no-interaction' => true,
        ])->assertFailed();
    } finally {
        putenv('TEST_EMPTY_ROOT_PASSWORD');
    }

    expect(User::query()->where('email', 'vazio@abrasil.test')->exists())->toBeFalse();
});

test('a password shorter than the minimum is refused in non-interactive mode', function () {
    putenv('TEST_SHORT_ROOT_PASSWORD=curta');

    try {
        $this->artisan('platform:root-admin', [
            'email' => 'curta@abrasil.test',
            '--name' => 'Curta',
            '--password-env' => 'TEST_SHORT_ROOT_PASSWORD',
            '--no-interaction' => true,
        ])->assertFailed();
    } finally {
        putenv('TEST_SHORT_ROOT_PASSWORD');
    }

    expect(User::query()->where('email', 'curta@abrasil.test')->exists())->toBeFalse();
});
