<?php
/**
 * POST /api/admin/add-credits
 * Body: { "user_id": 123, "credits": 5 }
 *
 * Admin-only credit grant. Supports negative values to revoke. Logs
 * to alleogen_subscriptions_log with event='credits_reset' for audit.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('POST');
$admin = requireAdmin();

$body    = readJsonBody();
$userId  = (int) ($body['user_id'] ?? 0);
$credits = (int) ($body['credits'] ?? 0);

if ($userId <= 0) jsonResponse(['error' => 'user_id is required.'], 400);
if ($credits === 0) jsonResponse(['error' => 'credits must be non-zero.'], 400);

$pdo = getDB();
$user = getUserById($userId);
if ($user === null) jsonResponse(['error' => 'User not found.'], 404);

// Never drop below zero on a deduction.
$newBalance = max(0, (int) $user['credits_remaining'] + $credits);

$stmt = $pdo->prepare('UPDATE alleogen_users SET credits_remaining = :c WHERE id = :id');
$stmt->execute([':c' => $newBalance, ':id' => $userId]);

logSubscriptionEvent(
    $userId,
    'credits_reset',
    $user['plan'],
    $user['plan'],
    $credits,
    [
        'actor'    => 'admin',
        'admin_id' => (int) $admin['id'],
        'before'   => (int) $user['credits_remaining'],
        'after'    => $newBalance,
    ]
);

jsonResponse([
    'success'           => true,
    'credits_remaining' => $newBalance,
], 200);
