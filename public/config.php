<?php
// Konfigurasi dashboard PHP native untuk local Laragon maupun shared hosting/cPanel.
session_start();
date_default_timezone_set('Asia/Jakarta');

function env_load(string $file): void {
    if (!is_file($file)) return;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $_ENV[$key] = trim($value, "\"'");
    }
}

env_load(__DIR__ . '/.env');

function env(string $key, ?string $default = null): ?string {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

$appEnv = strtolower((string)env('APP_ENV', 'production'));
$isLocal = in_array($appEnv, ['local', 'development'], true);
$dbHost = env('DB_HOST', $isLocal ? '127.0.0.1' : 'localhost');
$dbPort = env('DB_PORT', $isLocal ? '3307' : '3306');
$dbName = env('DB_NAME', 'bot_wa_v1');
$dbUser = env('DB_USER', $isLocal ? 'root' : '');
$dbPass = env('DB_PASS', '');

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    $detail = $isLocal ? ' Detail local: ' . $e->getMessage() : '';
    exit('Koneksi database gagal. Periksa public/.env.' . $detail);
}
