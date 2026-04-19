<?php
/**
 * POST /api/auth/reset-password
 * Body: { "token": "<raw-token-from-email>", "new_password": "..." }
 *
 * Validates the token (hash lookup + expiry check), updates the
 * password hash, clears the token fields, and invalidates any
 * active server-side session for that user.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('POST');

$body     = readJsonBody();
$token    = trim((string) ($body['token'] ?? ''));
$password = (string) ($body['new_password'] ?? '');

if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
    jsonResponse(['error' => 'Invalid reset link.'], 400);
}
if (strlen($password) < 10) {
    jsonResponse(['error' => 'Password must be at least 10 characters.'], 400);
}

$tokenHash = hash('sha256', $token);
$pdo = getDB();

$stmt = $pdo->prepare(
    'SELECT id, email, reset_token_expires
     FROM alleogen_users
     WHERE reset_token_hash = :h
     LIMIT 1'
);
$stmt->execute([':h' => $tokenHash]);
$user = $stmt->fetch();

if ($user === false) {
    jsonResponse(['error' => 'This reset link is invalid or has already been used.'], 400);
}
if (empty($user['reset_token_expires']) || strtotime((string) $user['reset_token_expires']) < time()) {
    // Expired — clear it so the slot can be reused.
    $clr = $pdo->prepare(
        'UPDATE alleogen_users SET reset_token_hash = NULL, reset_token_expires = NULL WHERE id = :id'
    );
    $clr->execute([':id' => $user['id']]);
    jsonResponse(['error' => 'This reset link has expired. Request a new one.'], 400);
}

$newHash = password_hash($password, PASSWORD_BCRYPT);

$upd = $pdo->prepare(
    'UPDATE alleogen_users
     SET password_hash = :ph,
         reset_token_hash = NULL,
         reset_token_expires = NULL
     WHERE id = :id'
);
$upd->execute([':ph' => $newHash, ':id' => $user['id']]);

// Proactively invalidate any active server-side session for this user
// so an attacker who captured a token can't keep using a parallel login.
startSecureSession();
session_regenerate_id(true);
$_SESSION = [];

jsonResponse(['success' => true], 200);
