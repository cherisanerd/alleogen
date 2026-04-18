<?php
/**
 * POST /api/payments/create-checkout
 * Body: { "package_tier": "basic" | "complete", "website_url": "...", "business_name": "...", "email": "..." }
 *
 * Creates a pending generation row and returns a Stripe Checkout URL.
 * On success the user is redirected to ${APP_URL}/payment-success?token=...
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../vendor/autoload.php';

requireMethod('POST');

$body = readJsonBody();
$tier = (string) ($body['package_tier'] ?? '');
if (!in_array($tier, ['basic', 'complete'], true)) {
    jsonResponse(['error' => 'Invalid package_tier. Must be "basic" or "complete".'], 400);
}

$priceId = getSetting("stripe_price_onetime_{$tier}");
if ($priceId === '') {
    jsonResponse(['error' => "Stripe price ID for {$tier} is not configured."], 500);
}

$stripeKey = secret('stripe_secret_key');
if ($stripeKey === '') {
    jsonResponse(['error' => 'Stripe is not configured.'], 500);
}

$websiteUrl  = trim((string) ($body['website_url'] ?? ''));
$businessName = trim((string) ($body['business_name'] ?? ''));
$email       = trim((string) ($body['email'] ?? ''));
if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    jsonResponse(['error' => 'Invalid email.'], 400);
}

// Create pending generation row — we'll fill in payment details on webhook.
$accessToken = generateAccessToken();
$pdo = getDB();
$stmt = $pdo->prepare(
    'INSERT INTO alleogen_generations
        (access_token, website_url, business_name, package_tier, status, purchase_type, payment_method, payment_email)
     VALUES (:token, :url, :name, :tier, :status, :ptype, :pmethod, :email)'
);
$stmt->execute([
    ':token'   => $accessToken,
    ':url'     => $websiteUrl,
    ':name'    => $businessName !== '' ? $businessName : null,
    ':tier'    => $tier,
    ':status'  => 'pending_payment',
    ':ptype'   => 'one_time',
    ':pmethod' => 'stripe',
    ':email'   => $email !== '' ? $email : null,
]);
$generationId = (int) $pdo->lastInsertId();

try {
    \Stripe\Stripe::setApiKey($stripeKey);
    $session = \Stripe\Checkout\Session::create([
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price'    => $priceId,
            'quantity' => 1,
        ]],
        'mode'        => 'payment',
        'success_url' => APP_URL . '/payment-success?token=' . $accessToken,
        'cancel_url'  => APP_URL . '/pricing',
        'metadata'    => [
            'generation_id' => (string) $generationId,
            'access_token'  => $accessToken,
            'package_tier'  => $tier,
        ],
        'customer_email' => $email !== '' ? $email : null,
    ]);
} catch (\Stripe\Exception\ApiErrorException $e) {
    logError('billing', 'Stripe checkout creation failed: ' . $e->getMessage(), null, [
        'tier' => $tier,
        'generation_id' => $generationId,
    ]);
    jsonResponse(['error' => 'Payment provider error. Please try again.'], 502);
}

jsonResponse([
    'url'            => $session->url,
    'generation_id'  => $generationId,
    'access_token'   => $accessToken,
], 200);
