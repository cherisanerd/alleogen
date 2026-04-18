<?php
/**
 * GET /api/admin/settings/list
 *
 * Returns all admin-configurable settings (non-secret only). Secrets
 * live in config.local.php and are intentionally never exposed here.
 * Grouped by setting_group for easier UI rendering.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('GET');
requireAdmin();

$stmt = getDB()->query('SELECT setting_key, setting_value, label, setting_group, updated_at FROM alleogen_settings ORDER BY setting_group, setting_key');
$rows = $stmt->fetchAll();

$byGroup = [];
foreach ($rows as $r) {
    $group = $r['setting_group'] ?: 'general';
    $byGroup[$group] ??= [];
    $byGroup[$group][] = $r;
}

jsonResponse([
    'settings'  => $rows,
    'by_group'  => $byGroup,
], 200);
