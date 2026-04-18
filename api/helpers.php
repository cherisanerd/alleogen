<?php
/**
 * Shared helpers: JSON responses, settings cache, tokens, logging.
 */

declare(strict_types=1);

/**
 * Send a JSON response and exit. Always use this for API endpoints.
 *
 * @param array<string, mixed> $payload
 */
function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Read request body as JSON. Errors → 400.
 *
 * @return array<string, mixed>
 */
function readJsonBody(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') return [];
    $decoded = json_decode($raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(['error' => 'Invalid JSON body.'], 400);
    }
    return is_array($decoded) ? $decoded : [];
}

/**
 * Read an admin-configurable setting from alleogen_settings.
 * Cached for the life of the request. Returns $default if missing.
 */
function getSetting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            $stmt = getDB()->query('SELECT setting_key, setting_value FROM alleogen_settings');
            foreach ($stmt as $row) {
                $cache[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Throwable $e) {
            // Don't crash early on fresh installs — just return defaults.
            error_log('alleogen: settings load failed: ' . $e->getMessage());
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * Update (upsert) a single admin-configurable setting. Does not refresh
 * the in-request cache — call this once near the end of a write path.
 */
function setSetting(string $key, string $value, ?string $label = null, ?string $group = null): void
{
    $sql = 'INSERT INTO alleogen_settings (setting_key, setting_value, label, setting_group) '
         . 'VALUES (:k, :v, :l, :g) '
         . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)';
    $stmt = getDB()->prepare($sql);
    $stmt->execute([
        ':k' => $key,
        ':v' => $value,
        ':l' => $label ?? $key,
        ':g' => $group ?? 'general',
    ]);
}

/**
 * Cryptographically random URL-safe access token (64 hex chars).
 */
function generateAccessToken(): string
{
    return bin2hex(random_bytes(32));
}

/**
 * Record an error in alleogen_error_logs. Never throws.
 *
 * @param array<string, mixed>|null $requestData
 */
function logError(string $source, string $message, ?int $userId = null, ?array $requestData = null, ?string $stackTrace = null): void
{
    try {
        $stmt = getDB()->prepare(
            'INSERT INTO alleogen_error_logs (source, message, stack_trace, user_id, request_data)
             VALUES (:source, :message, :stack, :uid, :data)'
        );
        $stmt->execute([
            ':source'  => $source,
            ':message' => $message,
            ':stack'   => $stackTrace,
            ':uid'     => $userId,
            ':data'    => $requestData !== null ? json_encode($requestData) : null,
        ]);
    } catch (Throwable $e) {
        error_log('alleogen: logError failed — ' . $e->getMessage() . ' while logging: ' . $message);
    }
}

/**
 * Record a subscription lifecycle event.
 *
 * @param array<string, mixed>|null $ghlEventData
 */
function logSubscriptionEvent(
    int $userId,
    string $event,
    ?string $planFrom,
    ?string $planTo,
    ?int $creditsGranted,
    ?array $ghlEventData = null
): void {
    try {
        $stmt = getDB()->prepare(
            'INSERT INTO alleogen_subscriptions_log
                (user_id, event, plan_from, plan_to, credits_granted, ghl_event_data)
             VALUES (:uid, :event, :pf, :pt, :credits, :data)'
        );
        $stmt->execute([
            ':uid'     => $userId,
            ':event'   => $event,
            ':pf'      => $planFrom,
            ':pt'      => $planTo,
            ':credits' => $creditsGranted,
            ':data'    => $ghlEventData !== null ? json_encode($ghlEventData) : null,
        ]);
    } catch (Throwable $e) {
        error_log('alleogen: logSubscriptionEvent failed — ' . $e->getMessage());
    }
}

/**
 * Lookup a user row by id. Returns null if not found.
 *
 * @return array<string, mixed>|null
 */
function getUserById(int $id): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM alleogen_users WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Lookup a user row by email. Returns null if not found.
 *
 * @return array<string, mixed>|null
 */
function getUserByEmail(string $email): ?array
{
    $stmt = getDB()->prepare('SELECT * FROM alleogen_users WHERE email = :email');
    $stmt->execute([':email' => $email]);
    $row = $stmt->fetch();
    return $row === false ? null : $row;
}

/**
 * Current request IP, honoring a single trusted proxy layer if present.
 */
function clientIp(): string
{
    $forwarded = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
    if ($forwarded !== '') {
        $parts = array_map('trim', explode(',', $forwarded));
        $first = $parts[0] ?? '';
        if (filter_var($first, FILTER_VALIDATE_IP) !== false) return $first;
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}
