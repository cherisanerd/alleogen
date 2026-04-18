<?php
/**
 * POST /api/generations/generate
 * Body: { "generation_id": 123 }  OR  Authorization: Bearer <access_token>
 *
 * Consumes a credit (if subscriber) and builds the package. Writes the
 * zip to STORAGE_PATH and the files_data JSON to the DB.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/generator.php';

requireMethod('POST');

$body = readJsonBody();

// Resolve which generation we're building. Subscribers send id;
// one-timers use Authorization: Bearer <token>.
$pdo = getDB();

$genId = isset($body['generation_id']) ? (int) $body['generation_id'] : 0;
$auth = authenticateRequest();

if ($genId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM alleogen_generations WHERE id = :id');
    $stmt->execute([':id' => $genId]);
    $gen = $stmt->fetch();
    if ($gen === false) jsonResponse(['error' => 'Generation not found.'], 404);

    if ($auth === null || $auth['type'] !== 'session') {
        jsonResponse(['error' => 'Authentication required.'], 401);
    }
    $user = getUserById((int) $auth['user_id']);
    if ($user === null || (empty($user['is_admin']) && (int) ($gen['user_id'] ?? 0) !== (int) $user['id'])) {
        jsonResponse(['error' => 'Access denied.'], 403);
    }
} else {
    // Token path (one-timer).
    if ($auth === null || $auth['type'] !== 'token') {
        jsonResponse(['error' => 'Authentication required.'], 401);
    }
    $stmt = $pdo->prepare('SELECT * FROM alleogen_generations WHERE access_token = :t');
    $stmt->execute([':t' => $auth['access_token']]);
    $gen = $stmt->fetch();
    if ($gen === false) jsonResponse(['error' => 'Generation not found.'], 404);
    $user = null;
}

if ($gen['status'] === 'generating') {
    jsonResponse(['error' => 'Generation is already in progress.'], 409);
}

// Consume a credit for subscribers on their first generation of this row.
$needsCredit = ($gen['purchase_type'] === 'subscription' && empty($gen['generated_at']));
if ($needsCredit && $user !== null) {
    if ($user['subscription_status'] !== 'active') {
        jsonResponse(['error' => 'Active subscription required.'], 402);
    }
    if ((int) $user['credits_remaining'] <= 0) {
        $reset = date('F j', strtotime((string) $user['credits_reset_at']) ?: time());
        jsonResponse(['error' => "No credits remaining. Credits reset on {$reset}."], 402);
    }
    $stmt = $pdo->prepare(
        'UPDATE alleogen_users SET credits_remaining = credits_remaining - 1
         WHERE id = :id AND credits_remaining > 0'
    );
    $stmt->execute([':id' => $user['id']]);
    if ($stmt->rowCount() === 0) {
        jsonResponse(['error' => 'Credit consumption failed. Please refresh and try again.'], 409);
    }
}

// Flip status to generating.
$stmt = $pdo->prepare("UPDATE alleogen_generations SET status = 'generating' WHERE id = :id");
$stmt->execute([':id' => $gen['id']]);

// Load the analysis (if any) for scraped context.
$analysis = [];
if (!empty($gen['analysis_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM alleogen_analyses WHERE id = :id');
    $stmt->execute([':id' => $gen['analysis_id']]);
    $a = $stmt->fetch();
    if ($a !== false) $analysis = $a;
}

// questionnaire_data lives as JSON in the DB.
$questionnaireRaw = $gen['questionnaire_data'] ?? null;
$data = [];
if (is_string($questionnaireRaw) && $questionnaireRaw !== '') {
    $decoded = json_decode($questionnaireRaw, true);
    if (is_array($decoded)) $data = $decoded;
} elseif (is_array($questionnaireRaw)) {
    $data = $questionnaireRaw;
}

try {
    $result = generateFiles($gen, $analysis, $data);
} catch (Throwable $e) {
    logError('generator', 'generateFiles failed: ' . $e->getMessage(), $user['id'] ?? null, ['generation_id' => $gen['id']]);
    $stmt = $pdo->prepare("UPDATE alleogen_generations SET status = 'failed' WHERE id = :id");
    $stmt->execute([':id' => $gen['id']]);
    jsonResponse(['error' => 'File generation failed.'], 500);
}

// Persist the zip to STORAGE_PATH. Filename prefixed with the generation
// id so two packages for the same business can coexist.
if (!is_dir(STORAGE_PATH)) {
    @mkdir(STORAGE_PATH, 0750, true);
}
$onDiskName = (int) $gen['id'] . '_' . $result['zip_filename'];
$diskPath = rtrim(STORAGE_PATH, '/') . '/' . $onDiskName;
if (file_put_contents($diskPath, $result['zip_bytes']) === false) {
    logError('generator', "Could not write zip to {$diskPath}", $user['id'] ?? null, ['generation_id' => $gen['id']]);
    $stmt = $pdo->prepare("UPDATE alleogen_generations SET status = 'failed' WHERE id = :id");
    $stmt->execute([':id' => $gen['id']]);
    jsonResponse(['error' => 'File generation failed (storage).'], 500);
}

$stmt = $pdo->prepare(
    "UPDATE alleogen_generations SET
        status = 'completed',
        files_data = :files,
        zip_filename = :zf,
        generated_at = NOW()
     WHERE id = :id"
);
$stmt->execute([
    ':files' => json_encode($result['files_data'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ':zf'    => $onDiskName,
    ':id'    => $gen['id'],
]);

jsonResponse([
    'success'      => true,
    'file_count'   => count($result['files_data']),
    'zip_filename' => $result['zip_filename'],
], 200);
