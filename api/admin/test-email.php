<?php
/**
 * POST /api/admin/test-email
 * Body: { "to": "you@example.com" }
 *
 * Admin-only. Sends a short test message via the configured SMTP transport
 * so deliverability problems can be diagnosed without running a Stripe or
 * GHL flow. The response surfaces the underlying error from the mailer when
 * delivery fails — pair with /api/admin/error-logs for the full record.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer.php';

requireMethod('POST');
requireAdmin();

$body = readJsonBody();
$to   = trim((string) ($body['to'] ?? ''));

if ($to === '' || filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse(['error' => 'Valid "to" email is required.'], 400);
}

$subject = 'alleogen SMTP test';
$message = "This is a test message from the alleogen admin panel.\n"
         . "If you can read this, SMTP delivery is working.\n\n"
         . "Sent at: " . date('c') . "\n";

$ok = sendMail($to, $subject, $message);

if (!$ok) {
    jsonResponse([
        'success' => false,
        'error'   => 'Send failed. Check /api/admin/error-logs for the SMTP error.',
    ], 502);
}

jsonResponse(['success' => true, 'sent_to' => $to], 200);
