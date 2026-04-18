<?php
/**
 * GET /api/admin/generations/list[?user_id=...&status=...&sort=-created_at&limit=100]
 *
 * Admin view — returns generations across all users, with optional
 * filtering. Strips files_data blobs from the response to keep it small.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('GET');
requireAdmin();

$sort   = (string) ($_GET['sort'] ?? '-created_at');
$limit  = max(1, min(500, (int) ($_GET['limit'] ?? 100)));
$uid    = isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0;
$status = isset($_GET['status']) ? (string) $_GET['status'] : '';

$direction = str_starts_with($sort, '-') ? 'DESC' : 'ASC';
$column    = ltrim($sort, '-');
$allowed   = ['created_at', 'updated_at', 'generated_at', 'status', 'package_tier'];
if (!in_array($column, $allowed, true)) $column = 'created_at';

$sql = 'SELECT id, user_id, analysis_id, access_token, website_url, business_name,
               package_tier, status, purchase_type, payment_method, payment_id, payment_email,
               zip_filename, delete_files_on, download_count, downloaded_at,
               generated_at, created_at, updated_at
        FROM alleogen_generations WHERE 1=1';
$params = [];
if ($uid > 0) { $sql .= ' AND user_id = :uid'; $params[':uid'] = $uid; }
if ($status !== '') { $sql .= ' AND status = :status'; $params[':status'] = $status; }
$sql .= " ORDER BY {$column} {$direction} LIMIT {$limit}";

$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

foreach ($rows as &$r) { $r['created_date'] = $r['created_at']; }
unset($r);

jsonResponse(['generations' => $rows], 200);
