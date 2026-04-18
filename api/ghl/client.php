<?php
/**
 * GHL API client — App → GHL.
 *
 * Used when a subscriber initiates a change from inside the app
 * (e.g. "cancel my subscription" or "switch plan"). The app updates
 * the GHL contact via the API; GHL processes the billing change and
 * confirms back via webhook.
 *
 * Secrets come from config.local.php (ghl_api_key, ghl_location_id,
 * ghl_webhook_secret). Never stored in the DB.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

/**
 * Low-level GHL API call. Returns ['status' => int, 'data' => mixed].
 */
function ghlApiCall(string $endpoint, string $method = 'GET', ?array $body = null): array
{
    $apiKey = secret('ghl_api_key');
    if ($apiKey === '') {
        return ['status' => 500, 'data' => ['error' => 'GHL API key not configured.']];
    }

    $url = 'https://services.leadconnectorhq.com' . $endpoint;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer {$apiKey}",
            'Content-Type: application/json',
            'Accept: application/json',
            'Version: 2021-07-28',
        ],
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        logError('ghl', "cURL to GHL failed: {$err}", null, ['endpoint' => $endpoint, 'method' => $method]);
        return ['status' => 502, 'data' => ['error' => 'Upstream GHL request failed.']];
    }

    $decoded = json_decode((string) $response, true);
    return [
        'status' => (int) $httpCode,
        'data'   => $decoded ?? $response,
    ];
}

/**
 * Update a GHL contact's fields or custom fields.
 */
function updateGHLContact(string $contactId, array $fields): array
{
    return ghlApiCall("/contacts/{$contactId}", 'PUT', $fields);
}

/**
 * Map a GHL product/offer id to a local plan slug.
 */
function mapGHLProductToPlan(string $productId): string
{
    if ($productId !== '' && $productId === getSetting('ghl_product_agency')) return 'agency';
    if ($productId !== '' && $productId === getSetting('ghl_product_pro'))    return 'pro';
    return 'pro';
}
