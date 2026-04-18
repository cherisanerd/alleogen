<?php
/**
 * POST /api/auth/logout
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('POST');
startSecureSession();

$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();

jsonResponse(['success' => true], 200);
