<?php
/**
 * POST /api/generations/generate
 *
 * Runs the TypeScript generator (generator.ts) as a Deno subprocess,
 * pipes the generation + analysis + questionnaire data in, gets the
 * generated files + base64 zip back, writes the zip to STORAGE_PATH,
 * and updates the DB row.
 *
 * Host requirements:
 *   - `deno` on PATH (set DENO_PATH in config.local.php to override)
 *   - PHP shell_exec / proc_open permitted
 *
 * Auth: session (subscribers) or token (one-timers).
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('POST');

$body = readJsonBody();
$pdo = getDB();

// -------- Resolve the generation row + access control --------
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

// -------- Credit consumption (subscribers, first run) --------
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

// Flip status to generating so duplicate submissions 409.
$stmt = $pdo->prepare("UPDATE alleogen_generations SET status = 'generating' WHERE id = :id");
$stmt->execute([':id' => $gen['id']]);

// -------- Assemble Deno subprocess input --------
$analysis = null;
if (!empty($gen['analysis_id'])) {
    $stmt = $pdo->prepare('SELECT * FROM alleogen_analyses WHERE id = :id');
    $stmt->execute([':id' => $gen['analysis_id']]);
    $a = $stmt->fetch();
    if ($a !== false) $analysis = $a;
}

$questionnaireRaw = $gen['questionnaire_data'] ?? null;
$data = [];
if (is_string($questionnaireRaw) && $questionnaireRaw !== '') {
    $decoded = json_decode($questionnaireRaw, true);
    if (is_array($decoded)) $data = $decoded;
} elseif (is_array($questionnaireRaw)) {
    $data = $questionnaireRaw;
}

$input = [
    'generation' => $gen,
    'analysis'   => $analysis,
    'data'       => $data,
];

// -------- Spawn Deno --------
$denoPath = secret('deno_path', 'deno');
$scriptPath = __DIR__ . '/generator.ts';
if (!is_file($scriptPath)) {
    markGenerationFailed($pdo, (int) $gen['id'], 'generator.ts missing');
    jsonResponse(['error' => 'Generator script missing on server.'], 500);
}

$denoCmd = [
    $denoPath,
    'run',
    '--no-prompt',
    '--allow-read=' . $scriptPath,
    $scriptPath,
];

$descriptors = [
    0 => ['pipe', 'r'],  // stdin
    1 => ['pipe', 'w'],  // stdout
    2 => ['pipe', 'w'],  // stderr
];

$proc = proc_open($denoCmd, $descriptors, $pipes, __DIR__);
if (!is_resource($proc)) {
    markGenerationFailed($pdo, (int) $gen['id'], 'could not start deno');
    jsonResponse(['error' => 'Could not start generator subprocess.'], 500);
}

fwrite($pipes[0], json_encode($input, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
fclose($pipes[0]);

$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($proc);

if ($exitCode !== 0 || $stdout === false || $stdout === '') {
    logError('generator', "Deno generator exited {$exitCode}", $user['id'] ?? null, [
        'generation_id' => $gen['id'],
        'stderr'        => is_string($stderr) ? substr($stderr, 0, 2000) : '',
    ]);
    markGenerationFailed($pdo, (int) $gen['id'], 'deno exit ' . $exitCode);
    jsonResponse(['error' => 'File generation failed.'], 500);
}

$result = json_decode((string) $stdout, true);
if (!is_array($result) || empty($result['success']) || !is_array($result['files'] ?? null) || !is_string($result['zip_base64'] ?? null)) {
    logError('generator', 'Deno generator returned unexpected payload', $user['id'] ?? null, [
        'generation_id' => $gen['id'],
        'stdout_prefix' => substr((string) $stdout, 0, 500),
    ]);
    markGenerationFailed($pdo, (int) $gen['id'], 'bad generator output');
    jsonResponse(['error' => 'File generation failed.'], 500);
}

// -------- Persist files + zip --------
$zipBytes = base64_decode($result['zip_base64'], true);
if ($zipBytes === false || $zipBytes === '') {
    markGenerationFailed($pdo, (int) $gen['id'], 'bad zip encoding');
    jsonResponse(['error' => 'File generation failed.'], 500);
}

if (!is_dir(STORAGE_PATH)) {
    @mkdir(STORAGE_PATH, 0750, true);
}
$zipFilename = (string) ($result['zip_filename'] ?? 'package.zip');
$onDiskName = (int) $gen['id'] . '_' . basename($zipFilename);
$diskPath = rtrim(STORAGE_PATH, '/') . '/' . $onDiskName;

if (file_put_contents($diskPath, $zipBytes) === false) {
    logError('generator', "Could not write zip to {$diskPath}", $user['id'] ?? null, ['generation_id' => $gen['id']]);
    markGenerationFailed($pdo, (int) $gen['id'], 'could not write zip');
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
    ':files' => json_encode($result['files'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ':zf'    => $onDiskName,
    ':id'    => $gen['id'],
]);

jsonResponse([
    'success'      => true,
    'file_count'   => (int) ($result['file_count'] ?? count($result['files'])),
    'zip_filename' => $zipFilename,
], 200);


// -------- helpers --------
function markGenerationFailed(PDO $pdo, int $genId, string $why): void
{
    $stmt = $pdo->prepare("UPDATE alleogen_generations SET status = 'failed' WHERE id = :id");
    $stmt->execute([':id' => $genId]);
    // $why is already logged upstream via logError — duplicated here only
    // as a defensive annotation in case logError was bypassed.
    if ($why !== '') error_log("alleogen: generation {$genId} failed: {$why}");
}
