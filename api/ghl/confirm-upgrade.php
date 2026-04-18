<?php
/**
 * POST /api/ghl/confirm-upgrade
 *
 * Legacy compatibility shim. Upgrades are now finalized by the GHL
 * subscription.upgraded webhook — no server action is required when
 * the user lands on the post-checkout success page. We still expose
 * this endpoint so the old `base44.functions.invoke('processUpgrade')`
 * calls in UpgradeSuccess.jsx don't 404.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';

requireMethod('POST');

// Return the user's current subscription snapshot so the UpgradeSuccess
// page can show the updated plan/credits without waiting for a reload.
$auth = authenticateRequest();
$snapshot = null;
if ($auth !== null && $auth['type'] === 'session') {
    $user = getUserById((int) $auth['user_id']);
    if ($user !== null) {
        unset($user['password_hash']);
        $snapshot = [
            'plan'                => $user['plan'],
            'subscription_status' => $user['subscription_status'],
            'credits_remaining'   => (int) $user['credits_remaining'],
            'credits_per_cycle'   => (int) $user['credits_per_cycle'],
            'current_period_end'  => $user['current_period_end'],
        ];
    }
}

jsonResponse([
    'success'  => true,
    'message'  => 'Upgrade is processed by GoHighLevel; the webhook will finalize state shortly.',
    'snapshot' => $snapshot,
], 200);
