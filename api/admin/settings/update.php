<?php
/**
 * PUT /api/admin/settings/update
 * Body: { "updates": { "key": "value", "other_key": "other_value" } }
 *
 * Upserts one or more non-secret settings. Unknown keys are created
 * as a new row with the setting_group 'general' — the intent is for
 * ops to be able to add new admin knobs without touching SQL, and for
 * the UI to round-trip unknown values.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

requireMethod('PUT', 'POST');
requireAdmin();

$body = readJsonBody();
$updates = $body['updates'] ?? $body; // accept either envelope
if (!is_array($updates) || count($updates) === 0) {
    jsonResponse(['error' => 'updates map is required.'], 400);
}

// Refuse to write any key that looks like a secret — defense-in-depth.
// Real secrets are in config.local.php and must stay out of the DB.
$forbiddenSecretPatterns = ['/secret/i', '/_key$/i', '/api_key/i', '/webhook_secret/i'];
$pdo = getDB();
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare(
        'INSERT INTO alleogen_settings (setting_key, setting_value, label, setting_group)
         VALUES (:k, :v, :l, :g)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    foreach ($updates as $key => $value) {
        $key = (string) $key;
        if ($key === '' || strlen($key) > 100) continue;
        foreach ($forbiddenSecretPatterns as $pat) {
            if (preg_match($pat, $key) === 1) {
                $pdo->rollBack();
                jsonResponse(['error' => "Key '{$key}' looks like a secret — move it to config.local.php instead."], 400);
            }
        }
        $stmt->execute([
            ':k' => $key,
            ':v' => is_scalar($value) ? (string) $value : json_encode($value),
            ':l' => $key,
            ':g' => 'general',
        ]);
    }
    $pdo->commit();
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    logError('api', 'settings update failed: ' . $e->getMessage());
    jsonResponse(['error' => 'Could not update settings.'], 500);
}

jsonResponse(['success' => true, 'updated' => count($updates)], 200);
