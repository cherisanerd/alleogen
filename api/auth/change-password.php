<?php
/**
 * POST /api/auth/change-password
 * Body: { "current_password": "...", "new_password": "..." }
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('POST');

$user = requireUser();
$body = readJsonBody();

$current = (string) ($body['current_password'] ?? '');
$new     = (string) ($body['new_password'] ?? '');

if ($current === '' || $new === '') {
    jsonResponse(['error' => 'Current and new password are required.'], 400);
}
if (strlen($new) < 10) {
    jsonResponse(['error' => 'New password must be at least 10 characters.'], 400);
}
if ($new === $current) {
    jsonResponse(['error' => 'New password must differ from current password.'], 400);
}

// Re-fetch the row with password_hash — requireUser strips it.
$fresh = getUserById((int) $user['id']);
if ($fresh === null || !password_verify($current, (string) $fresh['password_hash'])) {
    usleep(random_int(150000, 400000));
    jsonResponse(['error' => 'Current password is incorrect.'], 401);
}

$stmt = getDB()->prepare('UPDATE alleogen_users SET password_hash = :h WHERE id = :id');
$stmt->execute([
    ':h'  => password_hash($new, PASSWORD_BCRYPT),
    ':id' => $user['id'],
]);

// Rotate session id for defense-in-depth.
startSecureSession();
session_regenerate_id(true);

jsonResponse(['success' => true], 200);
