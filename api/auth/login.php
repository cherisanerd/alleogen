<?php
/**
 * POST /api/auth/login
 * Body: { "email": "...", "password": "..." }
 *
 * Returns the user row (minus password_hash) on success.
 * Rate-limit: naive per-IP throttle via a short sleep on failure.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('POST');
startSecureSession();

$body = readJsonBody();
$email = trim((string) ($body['email'] ?? ''));
$password = (string) ($body['password'] ?? '');

if ($email === '' || $password === '') {
    jsonResponse(['error' => 'Email and password are required.'], 400);
}
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse(['error' => 'Invalid email.'], 400);
}

$user = getUserByEmail($email);

// Consistent-time failure response. Always run password_verify against
// *something* to avoid leaking account existence via response timing.
$dummyHash = '$2y$10$abcdefghijklmnopqrstuuJz3J9N3uZ3H9vWb6Z4J5P5y3K7aC8pF6';
$hash = $user['password_hash'] ?? $dummyHash;

if (!password_verify($password, $hash) || $user === null) {
    // Small constant-ish delay to blunt brute force from a single IP.
    usleep(random_int(150000, 400000));
    jsonResponse(['error' => 'Invalid email or password.'], 401);
}

// Bootstrap admin: if config.local.php defines bootstrap_admin_email and
// it matches this account, promote on login. One-way — won't demote.
$bootstrapAdmin = secret('bootstrap_admin_email');
if ($bootstrapAdmin !== '' && strcasecmp($bootstrapAdmin, (string) $user['email']) === 0 && empty($user['is_admin'])) {
    $stmt = getDB()->prepare('UPDATE alleogen_users SET is_admin = 1 WHERE id = :id');
    $stmt->execute([':id' => $user['id']]);
    $user['is_admin'] = 1;
}

// Rotate session id on login to prevent fixation.
session_regenerate_id(true);
$_SESSION['user_id']  = (int) $user['id'];
$_SESSION['email']    = $user['email'];
$_SESSION['is_admin'] = (bool) $user['is_admin'];
$_SESSION['plan']     = $user['plan'];

$stmt = getDB()->prepare('UPDATE alleogen_users SET last_login_at = NOW() WHERE id = :id');
$stmt->execute([':id' => $user['id']]);

unset($user['password_hash']);
jsonResponse(['success' => true, 'user' => $user], 200);
