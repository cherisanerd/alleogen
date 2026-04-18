<?php
/**
 * POST /api/admin/coupons/create
 * Body: {
 *   "code": "WELCOME3",
 *   "credits": 3,
 *   "coupon_type": "one_time_generation",  // optional
 *   "max_uses": 100,                       // optional
 *   "expires_at": "2026-12-31",            // optional
 *   "is_active": true
 * }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('POST');
requireAdmin();

$body = readJsonBody();
$code = strtoupper(trim((string) ($body['code'] ?? '')));
if ($code === '' || strlen($code) > 50) {
    jsonResponse(['error' => 'code is required (max 50 chars).'], 400);
}
if (!preg_match('/^[A-Z0-9_\-]+$/', $code)) {
    jsonResponse(['error' => 'code may only contain A-Z, 0-9, _ and -.'], 400);
}

$credits     = (int) ($body['credits'] ?? 1);
$couponType  = in_array(($body['coupon_type'] ?? ''), ['one_time_generation', 'subscription_trial', 'credits'], true)
    ? $body['coupon_type']
    : 'one_time_generation';
$trialDays   = isset($body['trial_days']) && $body['trial_days'] !== null ? (int) $body['trial_days'] : null;
$maxUses     = isset($body['max_uses'])   && $body['max_uses']   !== null ? (int) $body['max_uses']   : null;
$isActive    = isset($body['is_active']) ? (bool) $body['is_active'] : true;
$expiresAt   = isset($body['expires_at']) && $body['expires_at'] !== ''  ? (string) $body['expires_at'] : null;

$pdo = getDB();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO alleogen_coupons
            (code, coupon_type, credits, trial_days, max_uses, is_active, expires_at)
         VALUES (:code, :type, :credits, :trial, :max_uses, :active, :expires)'
    );
    $stmt->execute([
        ':code'     => $code,
        ':type'     => $couponType,
        ':credits'  => $credits,
        ':trial'    => $trialDays,
        ':max_uses' => $maxUses,
        ':active'   => $isActive ? 1 : 0,
        ':expires'  => $expiresAt,
    ]);
} catch (PDOException $e) {
    if ((int) $e->errorInfo[1] === 1062) {
        jsonResponse(['error' => 'A coupon with that code already exists.'], 409);
    }
    throw $e;
}

$id = (int) $pdo->lastInsertId();
$stmt = $pdo->prepare('SELECT * FROM alleogen_coupons WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
$row['uses_count']   = (int) ($row['times_used'] ?? 0);
$row['created_date'] = $row['created_at'];

jsonResponse(['success' => true, 'coupon' => $row], 200);
