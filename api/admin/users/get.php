<?php
/**
 * GET /api/admin/users/get?id={id}
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('GET');
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(['error' => 'id is required.'], 400);

$user = getUserById($id);
if ($user === null) jsonResponse(['error' => 'Not found.'], 404);

unset($user['password_hash']);
$user['role']           = !empty($user['is_admin']) ? 'admin' : 'user';
$user['is_super_admin'] = !empty($user['is_admin']);
$user['plan_type']      = $user['plan'] !== 'none' ? $user['plan'] : null;
$user['created_date']   = $user['created_at'];
$user['full_name']      = $user['name'];

jsonResponse(['user' => $user], 200);
