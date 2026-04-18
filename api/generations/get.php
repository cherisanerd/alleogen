<?php
/**
 * GET /api/generations/get?id={id}  OR  ?token={access_token}
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('GET');

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$token = isset($_GET['token']) ? (string) $_GET['token'] : '';

if ($id <= 0 && $token === '') {
    jsonResponse(['error' => 'id or token is required.'], 400);
}

$pdo = getDB();

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT * FROM alleogen_generations WHERE access_token = :t');
    $stmt->execute([':t' => $token]);
    $row = $stmt->fetch();
    if ($row === false) jsonResponse(['error' => 'Not found.'], 404);
    jsonResponse(['generation' => $row], 200);
}

$stmt = $pdo->prepare('SELECT * FROM alleogen_generations WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if ($row === false) jsonResponse(['error' => 'Not found.'], 404);

$auth = authenticateRequest();
if ($auth === null || $auth['type'] !== 'session') {
    jsonResponse(['error' => 'Not authenticated.'], 401);
}
$user = getUserById((int) $auth['user_id']);
if ($user === null || ((int) ($row['user_id'] ?? 0) !== (int) $user['id'] && empty($user['is_admin']))) {
    jsonResponse(['error' => 'Access denied.'], 403);
}

jsonResponse(['generation' => $row], 200);
