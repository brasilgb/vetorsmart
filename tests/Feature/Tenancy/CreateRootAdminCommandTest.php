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
        ->expectsOutput('Nome ou senha inválidos. A senha deve ter ao menos 12 caracteres.')
        ->assertFailed();

    expect(User::query()->where('email', 'fraco@abrasil.test')->exists())->toBeFalse();
});

test('the command refuses to run without an interactive terminal', function () {
    $this->artisan('platform:root-admin', ['email' => 'nao@abrasil.test', '--no-interaction' => true])
        ->assertFailed();

    expect(User::query()->where('email', 'nao@abrasil.test')->exists())->toBeFalse();
});
