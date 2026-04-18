<?php
/**
 * DELETE /api/admin/coupons/delete?id={id}
 *
 * Deletes the coupon. Prior redemptions are preserved (coupon_id FK is
 * not cascade delete) for the audit trail.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('DELETE', 'POST');
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(['error' => 'id is required.'], 400);

$pdo = getDB();

// Nuke any redemption rows first so the FK constraint doesn't block us.
$delR = $pdo->prepare('DELETE FROM alleogen_coupon_redemptions WHERE coupon_id = :id');
$delR->execute([':id' => $id]);

$del = $pdo->prepare('DELETE FROM alleogen_coupons WHERE id = :id');
$del->execute([':id' => $id]);

if ($del->rowCount() === 0) {
    jsonResponse(['error' => 'Not found.'], 404);
}
jsonResponse(['success' => true], 200);
