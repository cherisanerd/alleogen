<?php
/**
 * GET /api/generations/download?token={access_token}
 *   (subscribers can also use ?id={id} with session auth)
 *
 * Streams the generated zip. Increments download_count on each hit.
 * Never serves the zip directly — the storage/ directory is deny-all.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('GET');

$token = isset($_GET['token']) ? (string) $_GET['token'] : '';
$id    = isset($_GET['id'])    ? (int)    $_GET['id']    : 0;

if ($token === '' && $id <= 0) {
    jsonResponse(['error' => 'token or id is required.'], 400);
}

$pdo = getDB();

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT * FROM alleogen_generations WHERE access_token = :t');
    $stmt->execute([':t' => $token]);
    $gen = $stmt->fetch();
    if ($gen === false) jsonResponse(['error' => 'Not found.'], 404);
} else {
    $stmt = $pdo->prepare('SELECT * FROM alleogen_generations WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $gen = $stmt->fetch();
    if ($gen === false) jsonResponse(['error' => 'Not found.'], 404);

    $user = requireUser();
    if (empty($user['is_admin']) && (int) ($gen['user_id'] ?? 0) !== (int) $user['id']) {
        jsonResponse(['error' => 'Access denied.'], 403);
    }
}

if (empty($gen['zip_filename']) || $gen['status'] !== 'completed') {
    jsonResponse(['error' => 'Download not available.'], 409);
}

// Defense-in-depth: zip_filename should never contain a directory separator,
// but clamp to basename anyway.
$diskName = basename((string) $gen['zip_filename']);
$diskPath = rtrim(STORAGE_PATH, '/') . '/' . $diskName;
if (!is_file($diskPath)) {
    logError('api', "Zip missing on disk: {$diskPath}", null, ['generation_id' => $gen['id']]);
    jsonResponse(['error' => 'File is no longer available.'], 410);
}

// Bump download counter best-effort.
$stmt = $pdo->prepare(
    'UPDATE alleogen_generations SET download_count = download_count + 1, downloaded_at = NOW()
     WHERE id = :id'
);
$stmt->execute([':id' => $gen['id']]);

// Stream the file.
$size = filesize($diskPath);
$downloadName = preg_replace('/^\d+_/', '', $diskName); // strip leading "<id>_" prefix
header('Content-Type: application/zip');
header('Content-Length: ' . ($size !== false ? (string) $size : ''));
header('Content-Disposition: attachment; filename="' . addslashes((string) $downloadName) . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$fh = fopen($diskPath, 'rb');
if ($fh === false) {
    jsonResponse(['error' => 'Could not open file.'], 500);
}
while (!feof($fh)) {
    echo fread($fh, 8192);
    flush();
}
fclose($fh);
exit;
