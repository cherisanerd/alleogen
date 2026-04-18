<?php
/**
 * GET /api/admin/coupons/list[?sort=-created_at&limit=100]
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('GET');
requireAdmin();

$sort  = (string) ($_GET['sort'] ?? '-created_at');
$limit = max(1, min(500, (int) ($_GET['limit'] ?? 100)));

$direction = str_starts_with($sort, '-') ? 'DESC' : 'ASC';
$column    = ltrim($sort, '-');
$allowed   = ['created_at', 'code', 'times_used', 'is_active'];
if (!in_array($column, $allowed, true)) $column = 'created_at';

$stmt = getDB()->prepare("SELECT * FROM alleogen_coupons ORDER BY {$column} {$direction} LIMIT {$limit}");
$stmt->execute();
$rows = $stmt->fetchAll();

// The legacy Admin.jsx renders `uses_count` and `created_date` fields.
foreach ($rows as &$r) {
    $r['uses_count']   = (int) ($r['times_used'] ?? 0);
    $r['created_date'] = $r['created_at'];
    $r['is_active']    = (bool) $r['is_active'];
}
unset($r);

jsonResponse(['coupons' => $rows], 200);
