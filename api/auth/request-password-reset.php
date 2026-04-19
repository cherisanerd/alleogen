<?php
/**
 * POST /api/auth/request-password-reset
 * Body: { "email": "you@example.com" }
 *
 * Generates a cryptographically random 64-character reset token,
 * stores its SHA-256 hash in the user row with a 60-minute expiry,
 * and emails the raw token as a reset link.
 *
 * Security notes:
 *   - Always returns 200 whether or not the email exists. This
 *     prevents anyone from probing which addresses have accounts.
 *   - Constant small delay on "no such user" so the timing of the
 *     response doesn't reveal account existence either.
 *   - The raw token never appears in the DB. Only its hash does,
 *     so a DB dump can't be used to reset anyone's password.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer.php';

requireMethod('POST');

$body  = readJsonBody();
$email = trim((string) ($body['email'] ?? ''));

if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse(['error' => 'A valid email is required.'], 400);
}

$user = getUserByEmail($email);
if ($user === null) {
    // Constant-ish delay to mask non-existent account.
    usleep(random_int(100000, 300000));
    jsonResponse(['success' => true], 200);
}

// 32 raw bytes → 64-char hex token. This is what we email.
$rawToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);
$expiresAt = date('Y-m-d H:i:s', time() + 60 * 60); // 1 hour

$stmt = getDB()->prepare(
    'UPDATE alleogen_users
     SET reset_token_hash = :h, reset_token_expires = :exp
     WHERE id = :id'
);
$stmt->execute([
    ':h'   => $tokenHash,
    ':exp' => $expiresAt,
    ':id'  => $user['id'],
]);

$base = rtrim(APP_URL, '/');
$resetUrl = "{$base}/account/reset?token=" . urlencode($rawToken);

sendPasswordResetEmail($email, $resetUrl);

jsonResponse(['success' => true], 200);
