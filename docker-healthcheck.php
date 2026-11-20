<?php

/*
 * Verificação de saúde dos containers do VetorSmart (web, worker e scheduler).
 *
 * PHP puro, sem iniciar o framework, para ser barato a cada 15 segundos. Confere o que o `php-fpm -t` não vê:
 * os caches gerados pelo entrypoint, a chave da aplicação e a conexão com o banco usando a configuração em cache,
 * que é a mesma que a aplicação usa. No container web, confere também se o PHP-FPM aceita conexões.
 *
 * Nenhuma mensagem inclui credenciais nem o texto do erro do driver.
 */

$base = '/var/www/html';

$fail = static function (string $reason): never {
    fwrite(STDERR, "unhealthy: {$reason}\n");
    exit(1);
};

$configFile = "{$base}/bootstrap/cache/config.php";

if (! is_file($configFile) || ! is_file("{$base}/bootstrap/cache/routes-v7.php")) {
    $fail('caches do Laravel ausentes');
}

$config = require $configFile;

if (! is_array($config) || empty($config['app']['key'])) {
    $fail('APP_KEY ausente na configuração em cache');
}

$default = $config['database']['default'] ?? null;
$db = $config['database']['connections'][$default] ?? null;

if (! is_array($db) || ($db['driver'] ?? null) !== 'mysql') {
    $fail('conexão de banco padrão inválida');
}

try {
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s', $db['host'], $db['port'] ?? 3306, $db['database']),
        (string) $db['username'],
        (string) $db['password'],
        [PDO::ATTR_TIMEOUT => 3, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $pdo->query('SELECT 1');
} catch (Throwable $e) {
    $fail('banco de dados inacessível (código '.$e->getCode().')');
}

// As imagens dos sites são lidas em JPG/PNG/WebP e gravadas em WebP: sem esses formatos no GD, todo envio falha.
if (! function_exists('imagewebp') || ! function_exists('imagecreatefromjpeg') || empty(gd_info()['WebP Support'])) {
    $fail('extensão GD sem suporte a JPEG ou WebP');
}

// PID 1 identifica o papel: no container web é o PHP-FPM, que precisa aceitar conexões FastCGI.
$command = (string) @file_get_contents('/proc/1/cmdline');

if (str_contains($command, 'php-fpm')) {
    $socket = @fsockopen('127.0.0.1', 9000, $errno, $errstr, 3);

    if ($socket === false) {
        $fail('PHP-FPM não aceita conexões');
    }

    fclose($socket);
}

echo "healthy\n";
