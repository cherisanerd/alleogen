<?php
/**
 * GET /api/admin/users/list[?sort=-created_at&limit=100&q=...]
 *
 * Admin-only. Returns all user rows (minus password_hash) with
 * optional email/name substring filter.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('GET');
requireAdmin();

$sort      = (string) ($_GET['sort']  ?? '-created_at');
$limit     = max(1, min(500, (int) ($_GET['limit'] ?? 100)));
$q         = trim((string) ($_GET['q'] ?? ''));

$direction = str_starts_with($sort, '-') ? 'DESC' : 'ASC';
$column    = ltrim($sort, '-');
$allowed   = ['created_at', 'updated_at', 'last_login_at', 'email', 'plan', 'subscription_status'];
if (!in_array($column, $allowed, true)) $column = 'created_at';

$sql = 'SELECT id, email, name, is_admin, ghl_contact_id, plan, subscription_status,
               subscription_anchor_day, subscription_started_at, current_period_end,
               credits_remaining, credits_per_cycle, credits_reset_at,
               delete_clock_active, delete_clock_start, delete_files_on,
               coupon_used, created_at, updated_at, last_login_at
        FROM alleogen_users';
$params = [];
if ($q !== '') {
    $sql .= ' WHERE email LIKE :q OR name LIKE :q';
    $params[':q'] = '%' . $q . '%';
}
$sql .= " ORDER BY {$column} {$direction} LIMIT {$limit}";

$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Expose a couple of aliases the existing Admin.jsx expects.
foreach ($rows as &$r) {
    $r['role'] = !empty($r['is_admin']) ? 'admin' : 'user';
    $r['is_super_admin'] = !empty($r['is_admin']);
    $r['plan_type'] = $r['plan'] !== 'none' ? $r['plan'] : null;
    $r['full_name'] = $r['name'];
    $r['created_date'] = $r['created_at'];
}
unset($r);

jsonResponse(['users' => $rows], 200);
