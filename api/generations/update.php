<?php
/**
 * PUT /api/generations/update?id={id}  OR  ?token={access_token}
 *
 * Updates questionnaire_data, package_tier, business_name, or website_url
 * on a pending generation. Does not touch status/payment/delete fields.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('PUT', 'POST');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
if ($id <= 0 && $token === '') {
    jsonResponse(['error' => 'id or token is required.'], 400);
}

$pdo = getDB();
if ($token !== '') {
    $stmt = $pdo->prepare('SELECT * FROM alleogen_generations WHERE access_token = :t');
    $stmt->execute([':t' => $token]);
} else {
    $stmt = $pdo->prepare('SELECT * FROM alleogen_generations WHERE id = :id');
    $stmt->execute([':id' => $id]);
}
$gen = $stmt->fetch();
if ($gen === false) jsonResponse(['error' => 'Not found.'], 404);

// Access control.
if ($id > 0) {
    $auth = authenticateRequest();
    if ($auth === null || $auth['type'] !== 'session') {
        jsonResponse(['error' => 'Not authenticated.'], 401);
    }
    $user = getUserById((int) $auth['user_id']);
    if ($user === null || (empty($user['is_admin']) && (int) ($gen['user_id'] ?? 0) !== (int) $user['id'])) {
        jsonResponse(['error' => 'Access denied.'], 403);
    }
}

$body = readJsonBody();
$updates = [];
$params = [':id' => $gen['id']];

if (array_key_exists('questionnaire_data', $body)) {
    $qd = $body['questionnaire_data'];
    $updates[] = 'questionnaire_data = :qd';
    $params[':qd'] = is_array($qd) ? json_encode($qd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : (string) $qd;
}
if (array_key_exists('package_tier', $body)) {
    $tier = (string) $body['package_tier'];
    if (!in_array($tier, ['basic', 'complete'], true)) {
        jsonResponse(['error' => 'Invalid package_tier.'], 400);
    }
    $updates[] = 'package_tier = :tier';
    $params[':tier'] = $tier;
}
if (array_key_exists('business_name', $body)) {
    $updates[] = 'business_name = :bn';
    $params[':bn'] = (string) $body['business_name'];
}
if (array_key_exists('website_url', $body)) {
    $updates[] = 'website_url = :url';
    $params[':url'] = (string) $body['website_url'];
}
if (array_key_exists('download_count', $body)) {
    $updates[] = 'download_count = :dc';
    $updates[] = 'downloaded_at = NOW()';
    $params[':dc'] = (int) $body['download_count'];
}

if (count($updates) === 0) {
    jsonResponse(['error' => 'No fields to update.'], 400);
}

$sql = 'UPDATE alleogen_generations SET ' . implode(', ', $updates) . ' WHERE id = :id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

jsonResponse(['success' => true], 200);
