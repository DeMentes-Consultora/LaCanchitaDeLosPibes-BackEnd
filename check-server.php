<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv::createImmutable(__DIR__);
    $dotenv->safeLoad();
}

function ok(bool $condition): string
{
    return $condition ? 'OK' : 'ERROR';
}

function envValue(string $key, string $default = ''): string
{
    return (string) ($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key) ?: $default);
}

$checks = [];
$checks[] = ['PHP >= 8.1', version_compare(PHP_VERSION, '8.1.0', '>=')];
$checks[] = ['mysqli', extension_loaded('mysqli')];
$checks[] = ['curl', extension_loaded('curl') || function_exists('curl_init')];
$checks[] = ['openssl', extension_loaded('openssl')];
$checks[] = ['vendor/autoload.php', file_exists(__DIR__ . '/vendor/autoload.php')];
$checks[] = ['src/Api', is_dir(__DIR__ . '/src/Api')];
$checks[] = ['src/ConectionBD', is_dir(__DIR__ . '/src/ConectionBD')];
$checks[] = ['src/Controllers', is_dir(__DIR__ . '/src/Controllers')];
$checks[] = ['.env', file_exists(__DIR__ . '/.env')];

$dbStatus = ['Conexión DB', false, 'Faltan credenciales'];
$dbHost = envValue('DB_HOST');
$dbUser = envValue('DB_USERNAME');
$dbPass = envValue('DB_PASSWORD');
$dbName = envValue('DB_NAME');
$dbCharset = envValue('DB_CHARSET', 'utf8mb4');

if ($dbHost !== '' && $dbUser !== '' && $dbName !== '') {
    try {
        $conn = @new mysqli($dbHost, $dbUser, $dbPass, $dbName);
        if (!$conn->connect_error) {
            $conn->set_charset($dbCharset);
            $dbStatus = ['Conexión DB', true, 'Conexión exitosa'];
            $conn->close();
        } else {
            $dbStatus = ['Conexión DB', false, $conn->connect_error];
        }
    } catch (Throwable $e) {
        $dbStatus = ['Conexión DB', false, $e->getMessage()];
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check Server - La Canchita</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 32px; background: #f6f7fb; color: #1f2937; }
        h1 { margin-bottom: 8px; }
        table { border-collapse: collapse; width: 100%; background: #fff; }
        th, td { padding: 12px; border: 1px solid #d1d5db; text-align: left; }
        th { background: #111827; color: #fff; }
        .ok { color: #15803d; font-weight: 700; }
        .error { color: #b91c1c; font-weight: 700; }
        .meta { margin: 16px 0 24px; }
        code { background: #e5e7eb; padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>La Canchita de los Pibes - Check Server</h1>
    <div class="meta">
        <div><strong>PHP:</strong> <?= htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>DB Host:</strong> <?= htmlspecialchars($dbHost !== '' ? $dbHost : 'sin configurar', ENT_QUOTES, 'UTF-8') ?></div>
        <div><strong>DB Name:</strong> <?= htmlspecialchars($dbName !== '' ? $dbName : 'sin configurar', ENT_QUOTES, 'UTF-8') ?></div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Chequeo</th>
                <th>Estado</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($checks as [$label, $status]): ?>
            <tr>
                <td><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                <td class="<?= $status ? 'ok' : 'error' ?>"><?= ok($status) ?></td>
                <td><?= $status ? 'Disponible' : 'No disponible' ?></td>
            </tr>
        <?php endforeach; ?>
            <tr>
                <td><?= htmlspecialchars($dbStatus[0], ENT_QUOTES, 'UTF-8') ?></td>
                <td class="<?= $dbStatus[1] ? 'ok' : 'error' ?>"><?= ok($dbStatus[1]) ?></td>
                <td><?= htmlspecialchars($dbStatus[2], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top: 24px;">Si todo está OK, probá también <code>/api/canchas.php</code> y luego eliminá este archivo del hosting.</p>
</body>
</html>
