<?php
/**
 * GET /api/generations/list?user_id=me[&analysis_id=...&sort=-created_at&limit=50]
 *
 * Subscriber-only. Returns the caller's generations. Admins can pass
 * any user_id. Filter by analysis_id for the questionnaire resume flow.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('GET');

$user = requireUser();

$wantedUserId = (int) $user['id'];
if (!empty($user['is_admin']) && isset($_GET['user_id']) && $_GET['user_id'] !== 'me') {
    $wantedUserId = (int) $_GET['user_id'];
}

$analysisId = isset($_GET['analysis_id']) ? (int) $_GET['analysis_id'] : 0;
$limit = max(1, min(200, (int) ($_GET['limit'] ?? 50)));

$sort = (string) ($_GET['sort'] ?? '-created_at');
$direction = str_starts_with($sort, '-') ? 'DESC' : 'ASC';
$column = ltrim($sort, '-');
$allowed = ['created_at', 'updated_at', 'generated_at', 'status', 'package_tier'];
if (!in_array($column, $allowed, true)) $column = 'created_at';

$sql = 'SELECT * FROM alleogen_generations WHERE user_id = :uid';
$params = [':uid' => $wantedUserId];
if ($analysisId > 0) {
    $sql .= ' AND analysis_id = :aid';
    $params[':aid'] = $analysisId;
}
$sql .= " ORDER BY {$column} {$direction} LIMIT {$limit}";

$stmt = getDB()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

jsonResponse(['generations' => $rows], 200);
