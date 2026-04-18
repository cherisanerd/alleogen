<?php
/**
 * Auth middleware. Two credential flavors:
 *
 *   1. Session cookie for subscribers who logged in with email/password.
 *   2. Bearer token (or ?token= query param) for one-timers whose
 *      access_token is tied to a single generation or analysis row.
 */

declare(strict_types=1);

/**
 * Start a hardened session. Safe to call multiple times per request.
 */
function startSecureSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;

    // Tighten cookie params before session_start.
    $secure = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_set_cookie_params([
        'lifetime' => 0,               // session cookie
        'path'     => '/',
        'domain'   => '',              // host-only
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('ALLEOGEN_SID');
    session_start();
}

/**
 * Inspect the request for a valid credential. Returns one of:
 *   - ['type' => 'session', 'user_id' => int]
 *   - ['type' => 'token',   'access_token' => string]
 *   - null (no credential supplied)
 *
 * This does NOT validate that the credential maps to a real user/row —
 * callers should use requireUser() / requireToken() to enforce that.
 *
 * @return array{type: string, user_id?: int, access_token?: string}|null
 */
function authenticateRequest(): ?array
{
    startSecureSession();

    if (!empty($_SESSION['user_id'])) {
        return ['type' => 'session', 'user_id' => (int) $_SESSION['user_id']];
    }

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authHeader === '' && function_exists('getallheaders')) {
        $all = getallheaders();
        $authHeader = $all['Authorization'] ?? $all['authorization'] ?? '';
    }
    if (is_string($authHeader) && preg_match('/Bearer\s+([A-Za-z0-9_\-]+)/', $authHeader, $m)) {
        return ['type' => 'token', 'access_token' => $m[1]];
    }

    $qToken = $_GET['token'] ?? null;
    if (is_string($qToken) && $qToken !== '' && preg_match('/^[A-Za-z0-9_\-]+$/', $qToken) === 1) {
        return ['type' => 'token', 'access_token' => $qToken];
    }

    return null;
}

/**
 * Require a logged-in session. Returns the user row.
 * Fails with 401 if no session, 403 if session refers to a missing user.
 *
 * @return array<string, mixed>
 */
function requireUser(): array
{
    $auth = authenticateRequest();
    if ($auth === null || $auth['type'] !== 'session') {
        jsonResponse(['error' => 'Not authenticated.'], 401);
    }
    $user = getUserById((int) $auth['user_id']);
    if ($user === null) {
        // Stale session — nuke it so the client re-auths.
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        jsonResponse(['error' => 'Session expired.'], 401);
    }
    return $user;
}

/**
 * Require a logged-in admin. Returns the user row.
 *
 * @return array<string, mixed>
 */
function requireAdmin(): array
{
    $user = requireUser();
    if (empty($user['is_admin'])) {
        jsonResponse(['error' => 'Admin access required.'], 403);
    }
    return $user;
}

/**
 * Allow either a logged-in subscriber OR a token that matches the
 * given generation. Returns ['user' => ...|null, 'generation' => row].
 *
 * @return array{user: array<string, mixed>|null, generation: array<string, mixed>}
 */
function requireGenerationAccess(string $token): array
{
    $auth = authenticateRequest();
    $pdo = getDB();

    $stmt = $pdo->prepare('SELECT * FROM alleogen_generations WHERE access_token = :t');
    $stmt->execute([':t' => $token]);
    $gen = $stmt->fetch();
    if ($gen === false) {
        jsonResponse(['error' => 'Generation not found.'], 404);
    }

    if ($auth !== null && $auth['type'] === 'session') {
        $user = getUserById((int) $auth['user_id']);
        // Admin: unrestricted. Otherwise must match user_id on the row.
        if ($user && (!empty($user['is_admin']) || (int) ($gen['user_id'] ?? 0) === (int) $user['id'])) {
            return ['user' => $user, 'generation' => $gen];
        }
    }

    // Token-based access: the token IS the credential.
    if ($auth !== null && $auth['type'] === 'token' && hash_equals((string) $gen['access_token'], (string) $auth['access_token'])) {
        return ['user' => null, 'generation' => $gen];
    }

    jsonResponse(['error' => 'Access denied.'], 403);
}

/**
 * Require POST (or any specific method). Call at the top of endpoints.
 */
function requireMethod(string ...$methods): void
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    foreach ($methods as $m) {
        if (strcasecmp($method, $m) === 0) return;
    }
    header('Allow: ' . implode(', ', array_map('strtoupper', $methods)));
    jsonResponse(['error' => 'Method not allowed.'], 405);
}
