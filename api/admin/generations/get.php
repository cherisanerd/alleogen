<?php
/**
 * GET /api/admin/generations/get?id={id}
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('GET');
requireAdmin();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) jsonResponse(['error' => 'id is required.'], 400);

$stmt = getDB()->prepare('SELECT * FROM alleogen_generations WHERE id = :id');
$stmt->execute([':id' => $id]);
$row = $stmt->fetch();
if ($row === false) jsonResponse(['error' => 'Not found.'], 404);

jsonResponse(['generation' => $row], 200);
