<?php

use App\Support\DatabaseGuard;

/*
 * The rules of App\Support\DatabaseGuard, checked as pure decisions. No database is touched.
 */

dataset('test_targets', [
    'banco de testes local, confirmado' => ['testing', 'mysql', '127.0.0.1', 'vetorsmart_test', 'vetorsmart_test', true],
    'host remoto, mesmo nome' => ['testing', 'mysql', 'db.empresa.example', 'vetorsmart_test', 'vetorsmart_test', false],
    'nome de produção, sem sufixo _test' => ['testing', 'mysql', '127.0.0.1', 'vetorsmart', 'vetorsmart', false],
    'nome _test sem confirmação explícita' => ['testing', 'mysql', '127.0.0.1', 'vetorsmart_test', null, false],
    'confirmação de outro banco' => ['testing', 'mysql', '127.0.0.1', 'vetorsmart_test', 'vetorsmart_homolog', false],
    'ambiente de produção' => ['production', 'mysql', '127.0.0.1', 'vetorsmart_test', 'vetorsmart_test', false],
    'banco de homologação' => ['testing', 'mysql', '127.0.0.1', 'vetorsmart_homolog', 'vetorsmart_homolog', false],
]);

test('automated tests run only when every rule passes', function (string $env, string $driver, ?string $host, string $db, ?string $confirmed, bool $allowed) {
    expect(DatabaseGuard::refusal($env, true, $driver, $host, $db, $confirmed, null) === null)->toBe($allowed);
})->with('test_targets');

dataset('destructive_targets', [
    'migrate:fresh em _test, confirmado' => ['migrate:fresh', '127.0.0.1', 'vetorsmart_test', 'vetorsmart_test', 'local', true],
    'db:wipe em _test, sem confirmação' => ['db:wipe', '127.0.0.1', 'vetorsmart_test', null, 'local', false],
    'migrate:reset em produção, mesmo _test e confirmado' => ['migrate:reset', '127.0.0.1', 'vetorsmart_test', 'vetorsmart_test', 'production', false],
    'migrate:refresh em host remoto' => ['migrate:refresh', 'db.empresa.example', 'vetorsmart_test', 'vetorsmart_test', 'local', false],
    'migrate:fresh em homologação, confirmado' => ['migrate:fresh', '127.0.0.1', 'vetorsmart_homolog', 'vetorsmart_homolog', 'local', false],
    'migrate:fresh em banco de produção' => ['migrate:fresh', '127.0.0.1', 'vetorsmart', 'vetorsmart', 'local', false],
]);

test('destructive commands run only on a confirmed local test database outside production', function (string $command, ?string $host, string $db, ?string $confirmed, string $env, bool $allowed) {
    $reason = DatabaseGuard::refusal($env, false, 'mysql', $host, $db, null, $confirmed, $command);

    expect($reason === null)->toBe($allowed);
})->with('destructive_targets');

test('a legitimate browser homologation database is not blocked for ordinary use', function () {
    expect(DatabaseGuard::refusal('local', false, 'mysql', '127.0.0.1', 'vetorsmart_homolog', null, null, null))->toBeNull()
        ->and(DatabaseGuard::refusal('local', false, 'mysql', '127.0.0.1', 'vetorsmart_homolog', null, null, 'migrate'))->toBeNull();
});

test('sqlite is allowed outside production and refused for tests in production', function () {
    expect(DatabaseGuard::refusal('testing', true, 'sqlite', null, ':memory:', null, null))->toBeNull()
        ->and(DatabaseGuard::refusal('production', true, 'sqlite', null, ':memory:', null, null))->not->toBeNull();
});

test('ordinary console commands run on sqlite even when the environment is production', function () {
    expect(DatabaseGuard::refusal('production', false, 'sqlite', null, ':memory:', null, null, 'optimize:clear'))->toBeNull()
        ->and(DatabaseGuard::refusal('production', false, 'sqlite', null, ':memory:', null, null, 'migrate:fresh'))->not->toBeNull()
        ->and(DatabaseGuard::refusal('production', false, 'mysql', '127.0.0.1', 'vetorsmart', null, null, 'optimize:clear'))->toBeNull();
});

test('an unknown driver is refused, never assumed safe', function () {
    expect(DatabaseGuard::refusal('testing', true, 'pgsql', '127.0.0.1', 'vetorsmart_test', 'vetorsmart_test', null))->not->toBeNull();
});
