<?php
/**
 * GET /api/auth/me
 *
 * Returns current subscriber session or token-based generation info.
 * This is the endpoint the React shell pings on load.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('GET');

$auth = authenticateRequest();

if ($auth === null) {
    jsonResponse(['authenticated' => false], 200);
}

if ($auth['type'] === 'session') {
    $user = getUserById((int) $auth['user_id']);
    if ($user === null) {
        jsonResponse(['authenticated' => false], 200);
    }
    // Strip sensitive fields before returning.
    unset($user['password_hash']);
    jsonResponse([
        'authenticated' => true,
        'type'          => 'session',
        'user'          => $user,
    ], 200);
}

// Token-based: look up matching generation (one-timer access).
$pdo = getDB();
$stmt = $pdo->prepare('SELECT id, access_token, user_id, website_url, business_name, package_tier, status, delete_files_on FROM alleogen_generations WHERE access_token = :t');
$stmt->execute([':t' => $auth['access_token']]);
$gen = $stmt->fetch();

if ($gen === false) {
    jsonResponse(['authenticated' => false, 'error' => 'Invalid token.'], 401);
}

jsonResponse([
    'authenticated' => true,
    'type'          => 'token',
    'generation'    => $gen,
], 200);
