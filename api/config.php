<?php
/**
 * AEO File Generator — public application config.
 *
 * This file is committed. Secrets (DB password, Stripe keys, GHL API key,
 * GHL webhook secret, GHL location ID, admin email) live in config.local.php,
 * which is gitignored and must be created on each deploy target from
 * config.local.example.php.
 */

declare(strict_types=1);

// -----------------------------------------------------------
// Locate + require the local secrets file. Fail fast in a
// production-safe way if it's missing.
// -----------------------------------------------------------
$localConfigPath = __DIR__ . '/config.local.php';
if (!is_readable($localConfigPath)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Server configuration error.']);
    error_log('alleogen: api/config.local.php missing or unreadable at ' . $localConfigPath);
    exit;
}
/** @var array<string, mixed> $LOCAL_CONFIG */
$LOCAL_CONFIG = require $localConfigPath;
if (!is_array($LOCAL_CONFIG)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Server configuration error.']);
    error_log('alleogen: api/config.local.php did not return an array');
    exit;
}

// -----------------------------------------------------------
// Application constants
// -----------------------------------------------------------
const ALLEOGEN_VERSION = '3.0.0';

if (!defined('APP_URL')) {
    define('APP_URL', (string) ($LOCAL_CONFIG['app_url'] ?? 'https://cherisanerd.com/tools/alleogen'));
}
if (!defined('STORAGE_PATH')) {
    // Zips live OUTSIDE the web-served api/ directory by default.
    // storage/zips/.htaccess also denies direct web access.
    $defaultStorage = dirname(__DIR__) . '/storage/zips';
    define('STORAGE_PATH', (string) ($LOCAL_CONFIG['storage_path'] ?? $defaultStorage));
}
if (!defined('APP_ENV')) {
    define('APP_ENV', (string) ($LOCAL_CONFIG['app_env'] ?? 'production'));
}

// -----------------------------------------------------------
// Secret accessor. Never log these values.
// -----------------------------------------------------------
function secret(string $key, string $default = ''): string
{
    global $LOCAL_CONFIG;
    $secrets = $LOCAL_CONFIG['secrets'] ?? [];
    return is_array($secrets) && array_key_exists($key, $secrets)
        ? (string) $secrets[$key]
        : $default;
}

// -----------------------------------------------------------
// PDO factory. One connection per request, cached.
// -----------------------------------------------------------
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    global $LOCAL_CONFIG;
    $db = $LOCAL_CONFIG['db'] ?? [];
    $host = (string) ($db['host'] ?? '127.0.0.1');
    $port = (int)    ($db['port'] ?? 3306);
    $name = (string) ($db['name'] ?? '');
    $user = (string) ($db['user'] ?? '');
    $pass = (string) ($db['pass'] ?? '');
    $sock = (string) ($db['socket'] ?? '');

    if ($sock !== '') {
        $dsn = "mysql:unix_socket={$sock};dbname={$name};charset=utf8mb4";
    } else {
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Database unavailable.']);
        error_log('alleogen: DB connection failed — ' . $e->getMessage());
        exit;
    }

    return $pdo;
}

// Pull in shared helpers + middleware so every endpoint that requires
// this file gets the full toolkit.
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/middleware.php';
