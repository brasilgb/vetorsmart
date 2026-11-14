<?php

namespace App\Support;

/**
 * Decides whether automated tests and destructive console commands may touch the configured database.
 *
 * The rule never depends on the database name alone. A destructive target must satisfy all of these:
 *  - the environment is not "production";
 *  - the connection is a local MySQL (loopback host), or SQLite;
 *  - the database name ends in "_test";
 *  - the operator explicitly named that same database in VETORSMART_DESTRUCTIVE_DATABASE.
 *
 * Automated tests additionally need VETORSMART_TEST_DATABASE with the same name, and never run in "production".
 * Everything is a pure function of its inputs, so the rules are tested without touching a database.
 */
final class DatabaseGuard
{
    /** Commands that erase data. */
    public const DESTRUCTIVE_COMMANDS = ['migrate:fresh', 'migrate:refresh', 'migrate:reset', 'db:wipe'];

    /** Hosts that count as this machine. A remote host is never a test or destructive target. */
    private const LOOPBACK_HOSTS = ['127.0.0.1', 'localhost', '::1'];

    /**
     * The refusal reason, or null when the operation is allowed.
     *
     * @param  string  $environment  APP_ENV
     * @param  bool  $runningTests  true while automated tests run
     * @param  string  $driver  database driver of the default connection (mysql, sqlite, ...)
     * @param  string|null  $host  database host of the default connection
     * @param  string|null  $database  database name of the default connection
     * @param  string|null  $explicitTestDatabase  VETORSMART_TEST_DATABASE
     * @param  string|null  $explicitDestructiveDatabase  VETORSMART_DESTRUCTIVE_DATABASE
     * @param  string|null  $command  console command name, when a destructive command is starting
     */
    public static function refusal(
        string $environment,
        bool $runningTests,
        string $driver,
        ?string $host,
        ?string $database,
        ?string $explicitTestDatabase,
        ?string $explicitDestructiveDatabase,
        ?string $command = null,
    ): ?string {
        $database = (string) $database;

        // Destructive commands never run in production, whatever the driver.
        if ($command !== null && in_array($command, self::DESTRUCTIVE_COMMANDS, true) && $environment === 'production') {
            return "Comando destrutivo [{$command}] recusado: APP_ENV=production.";
        }

        if ($driver === 'sqlite') {
            return $runningTests && $environment === 'production'
                ? 'Testes recusados: APP_ENV=production.'
                : null;
        }

        if ($driver !== 'mysql') {
            return "Driver [{$driver}] não tem regra de segurança definida; operação recusada.";
        }

        if ($runningTests) {
            if ($environment === 'production') {
                return 'Testes recusados: APP_ENV=production.';
            }

            if (! self::isLoopback($host)) {
                return "Testes recusados: o host [{$host}] não é local. Use um MySQL em 127.0.0.1.";
            }

            if (! str_ends_with($database, '_test')) {
                return "Testes recusados: o banco [{$database}] não termina em _test.";
            }

            if ($explicitTestDatabase !== $database) {
                return "Testes recusados: defina VETORSMART_TEST_DATABASE={$database} para confirmar o banco de testes.";
            }
        }

        if ($command !== null && in_array($command, self::DESTRUCTIVE_COMMANDS, true)) {
            if ($environment === 'production') {
                return "Comando destrutivo [{$command}] recusado: APP_ENV=production.";
            }

            if (! self::isLoopback($host)) {
                return "Comando destrutivo [{$command}] recusado: o host [{$host}] não é local.";
            }

            if (! str_ends_with($database, '_test')) {
                return "Comando destrutivo [{$command}] recusado: o banco [{$database}] não termina em _test.";
            }

            if ($explicitDestructiveDatabase !== $database) {
                return "Comando destrutivo [{$command}] recusado: defina VETORSMART_DESTRUCTIVE_DATABASE={$database} para confirmar.";
            }
        }

        return null;
    }

    private static function isLoopback(?string $host): bool
    {
        return $host !== null && in_array(strtolower($host), self::LOOPBACK_HOSTS, true);
    }
}
