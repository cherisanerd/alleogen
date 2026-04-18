<?php
/**
 * POST /api/ghl/subscribe-url
 * Body: { "plan": "pro" | "agency" }
 *
 * Returns the configured GoHighLevel subscribe URL for the requested
 * plan. The React frontend redirects the user there to complete the
 * purchase; GHL fires subscription.created back to the app via
 * /api/ghl/webhook once the charge clears.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('POST');

$body = readJsonBody();
$plan = strtolower(trim((string) ($body['plan'] ?? $body['package_tier'] ?? '')));

if (!in_array($plan, ['pro', 'agency'], true)) {
    jsonResponse(['error' => 'Invalid plan. Must be "pro" or "agency".'], 400);
}

$url = getSetting("ghl_subscribe_url_{$plan}");
if ($url === '') {
    jsonResponse(['error' => "Subscription link for {$plan} is not configured."], 503);
}

jsonResponse([
    'success' => true,
    'plan'    => $plan,
    'url'     => $url,
], 200);
