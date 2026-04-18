<?php
/**
 * POST /api/ghl/request-cancel
 * Body: { "reason": "...optional..." }
 *
 * Subscriber-initiated cancellation. Writes a custom field on the GHL
 * contact so a GHL workflow can pick up the request, process the
 * billing change, and fire subscription.canceled back to us. Also
 * flags the local user as 'canceling' so the UI can react immediately
 * even before GHL's webhook round-trips.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/client.php';

requireMethod('POST');

$user = requireUser();

if (empty($user['ghl_contact_id'])) {
    jsonResponse(['error' => 'No GHL contact linked to this account.'], 409);
}
if (($user['subscription_status'] ?? '') === 'canceled') {
    jsonResponse(['error' => 'Subscription is already canceled.'], 409);
}

$body = readJsonBody();
$reason = trim((string) ($body['reason'] ?? ''));

$result = updateGHLContact((string) $user['ghl_contact_id'], [
    'customField' => [
        ['key' => 'alleogen_cancel_requested', 'value' => date('Y-m-d H:i:s')],
        ['key' => 'alleogen_cancel_reason',    'value' => $reason !== '' ? substr($reason, 0, 500) : 'Not specified'],
    ],
]);

if ($result['status'] >= 400) {
    logError('ghl', 'Cancel request to GHL failed.', (int) $user['id'], [
        'status' => $result['status'],
        'data'   => $result['data'],
    ]);
    jsonResponse(['error' => 'Could not submit cancellation. Please try again or contact support.'], 502);
}

$stmt = getDB()->prepare("UPDATE alleogen_users SET subscription_status = 'canceling' WHERE id = :id");
$stmt->execute([':id' => $user['id']]);

jsonResponse([
    'success'             => true,
    'subscription_status' => 'canceling',
    'redirect_url'        => getSetting('ghl_cancel_redirect_url'),
], 200);
