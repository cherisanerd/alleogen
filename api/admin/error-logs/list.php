<?php
/**
 * GET /api/admin/error-logs/list[?source=&resolved=0&limit=100]
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('GET');
requireAdmin();

$source   = (string) ($_GET['source']   ?? '');
$resolved = isset($_GET['resolved'])    ? (int) $_GET['resolved'] : null;
$limit    = max(1, min(500, (int) ($_GET['limit'] ?? 100)));

$sql    = 'SELECT * FROM alleogen_error_logs WHERE 1=1';
$params = [];
if ($source !== '') {
    $sql .= ' AND source = :source';
    $params[':source'] = $source;
}
if ($resolved !== null) {
    $sql .= ' AND resolved = :resolved';
    $params[':resolved'] = $resolved ? 1 : 0;
}
$sql .= " ORDER BY created_at DESC LIMIT {$limit}";

$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonResponse(['error_logs' => $rows], 200);
