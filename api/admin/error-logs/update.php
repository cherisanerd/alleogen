<?php
/**
 * PUT /api/admin/error-logs/update?id={id}
 * Body: { "resolved": true | false }
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('PUT', 'POST');
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(['error' => 'id is required.'], 400);

$body = readJsonBody();
if (!array_key_exists('resolved', $body)) {
    jsonResponse(['error' => 'resolved field is required.'], 400);
}
$flag = $body['resolved'] ? 1 : 0;

$stmt = getDB()->prepare('UPDATE alleogen_error_logs SET resolved = :r WHERE id = :id');
$stmt->execute([':r' => $flag, ':id' => $id]);

jsonResponse(['success' => true], 200);
