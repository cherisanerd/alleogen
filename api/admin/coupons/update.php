<?php
/**
 * PUT /api/admin/coupons/update?id={id}
 * Body: any subset of { credits, max_uses, is_active, expires_at, coupon_type, trial_days }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('PUT', 'POST');
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(['error' => 'id is required.'], 400);

$body = readJsonBody();
$updates = [];
$params  = [':id' => $id];

if (array_key_exists('credits', $body)) {
    $updates[] = 'credits = :credits';
    $params[':credits'] = (int) $body['credits'];
}
if (array_key_exists('max_uses', $body)) {
    $updates[] = 'max_uses = :max_uses';
    $params[':max_uses'] = $body['max_uses'] === null ? null : (int) $body['max_uses'];
}
if (array_key_exists('is_active', $body)) {
    $updates[] = 'is_active = :active';
    $params[':active'] = $body['is_active'] ? 1 : 0;
}
if (array_key_exists('expires_at', $body)) {
    $updates[] = 'expires_at = :expires';
    $params[':expires'] = $body['expires_at'] === null || $body['expires_at'] === '' ? null : (string) $body['expires_at'];
}
if (array_key_exists('coupon_type', $body)
    && in_array($body['coupon_type'], ['one_time_generation', 'subscription_trial', 'credits'], true)) {
    $updates[] = 'coupon_type = :ctype';
    $params[':ctype'] = $body['coupon_type'];
}
if (array_key_exists('trial_days', $body)) {
    $updates[] = 'trial_days = :trial';
    $params[':trial'] = $body['trial_days'] === null ? null : (int) $body['trial_days'];
}

if (count($updates) === 0) {
    jsonResponse(['error' => 'No fields to update.'], 400);
}

$pdo = getDB();
$sql = 'UPDATE alleogen_coupons SET ' . implode(', ', $updates) . ' WHERE id = :id';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);

jsonResponse(['success' => true], 200);
