<?php
/**
 * POST /api/payments/confirm
 *
 * Legacy compatibility shim. Payment confirmation happens in the
 * Stripe webhook (api/payments/stripe-webhook.php), which flips the
 * generation row to 'questionnaire_incomplete' and emails the access
 * link. The PaymentSuccess.jsx page still posts here so it can read
 * back the current generation row without racing the webhook.
 *
 * Accepts { "token": "...", "session_id": "..." } and returns the
 * generation row (without files_data) if one matches.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('POST');

$body = readJsonBody();
$token = trim((string) ($body['token'] ?? ''));

if ($token === '') {
    jsonResponse(['error' => 'token is required.'], 400);
}

$pdo = getDB();
$stmt = $pdo->prepare(
    'SELECT id, access_token, website_url, business_name, package_tier, status,
            payment_email, delete_files_on, analysis_id, created_at, generated_at
     FROM alleogen_generations WHERE access_token = :t'
);
$stmt->execute([':t' => $token]);
$gen = $stmt->fetch();

if ($gen === false) {
    jsonResponse(['error' => 'Generation not found.'], 404);
}

jsonResponse([
    'success'    => true,
    'generation' => $gen,
], 200);
