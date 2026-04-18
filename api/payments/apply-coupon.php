<?php
/**
 * POST /api/payments/apply-coupon
 * Body: { "coupon_code": "...", "email": "..." }
 *
 * Redeems a one_time_generation coupon and issues an access token with
 * the same delete-clock rules as a paid one-time purchase. No Stripe
 * charge happens. Session users consume from their credits separately
 * via the generate endpoint (P5) — this is for free/coupon access only.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('POST');

$body = readJsonBody();
$code  = strtoupper(trim((string) ($body['coupon_code'] ?? '')));
$email = trim((string) ($body['email'] ?? ''));

if ($code === '') {
    jsonResponse(['error' => 'Coupon code is required.'], 400);
}
if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse(['error' => 'A valid email is required to redeem a coupon.'], 400);
}

$pdo = getDB();

$stmt = $pdo->prepare('SELECT * FROM alleogen_coupons WHERE code = :code');
$stmt->execute([':code' => $code]);
$coupon = $stmt->fetch();

if ($coupon === false || (int) $coupon['is_active'] !== 1) {
    jsonResponse(['error' => 'Invalid or inactive coupon.'], 404);
}
if (!empty($coupon['expires_at']) && strtotime((string) $coupon['expires_at']) < time()) {
    jsonResponse(['error' => 'This coupon has expired.'], 410);
}
if ($coupon['max_uses'] !== null && (int) $coupon['times_used'] >= (int) $coupon['max_uses']) {
    jsonResponse(['error' => 'This coupon has reached its usage limit.'], 410);
}

// One redemption per email.
$stmt = $pdo->prepare('SELECT id FROM alleogen_coupon_redemptions WHERE coupon_id = :cid AND email = :email');
$stmt->execute([':cid' => $coupon['id'], ':email' => $email]);
if ($stmt->fetch() !== false) {
    jsonResponse(['error' => 'This coupon has already been redeemed with that email.'], 409);
}

if ($coupon['coupon_type'] !== 'one_time_generation') {
    jsonResponse(['error' => 'This coupon type cannot be redeemed here.'], 400);
}

// Issue a pending-questionnaire generation row. Tier defaults to Basic
// unless the coupon has "_COMPLETE" in its code.
$tier = str_contains($code, 'COMPLETE') ? 'complete' : 'basic';
$accessToken = generateAccessToken();

$storageDays = (int) getSetting('onetime_storage_days', '30');
$deleteOn = date('Y-m-d H:i:s', strtotime("+{$storageDays} days") ?: time() + $storageDays * 86400);

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        "INSERT INTO alleogen_generations
            (access_token, website_url, package_tier, status, purchase_type, payment_method, payment_email, delete_files_on)
         VALUES (:token, '', :tier, 'questionnaire_incomplete', 'coupon', 'coupon', :email, :delon)"
    );
    $stmt->execute([
        ':token' => $accessToken,
        ':tier'  => $tier,
        ':email' => $email,
        ':delon' => $deleteOn,
    ]);
    $generationId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare('UPDATE alleogen_coupons SET times_used = times_used + 1 WHERE id = :id');
    $stmt->execute([':id' => $coupon['id']]);

    $stmt = $pdo->prepare(
        'INSERT INTO alleogen_coupon_redemptions (coupon_id, email) VALUES (:cid, :email)'
    );
    $stmt->execute([':cid' => $coupon['id'], ':email' => $email]);

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    logError('api', 'apply-coupon failed: ' . $e->getMessage(), null, ['code' => $code, 'email' => $email]);
    jsonResponse(['error' => 'Could not redeem coupon.'], 500);
}

jsonResponse([
    'success'       => true,
    'generation_id' => $generationId,
    'access_token'  => $accessToken,
    'package_tier'  => $tier,
    'delete_files_on' => $deleteOn,
], 200);
