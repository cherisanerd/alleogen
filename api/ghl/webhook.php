<?php
/**
 * POST /api/ghl/webhook
 *
 * GHL → App inbound webhook. Verifies an HMAC signature (shared secret
 * in config.local.php), then dispatches subscription lifecycle events
 * to their handler. All handlers are idempotent on replay.
 *
 * Expected events:
 *   - subscription.created
 *   - subscription.renewed
 *   - subscription.canceled
 *   - subscription.upgraded
 *   - subscription.downgraded
 *   - payment.failed
 *
 * GHL's actual payload shape varies by workflow. This handler is
 * defensive: it tolerates missing/extra fields and logs anything it
 * can't classify.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/client.php';

requireMethod('POST');

$payload = file_get_contents('php://input') ?: '';
$signature = $_SERVER['HTTP_X_GHL_SIGNATURE'] ?? $_SERVER['HTTP_X_GHL_SIGNATURE_256'] ?? '';

$webhookSecret = secret('ghl_webhook_secret');
if ($webhookSecret === '') {
    logError('ghl', 'GHL webhook hit without a configured webhook secret.');
    jsonResponse(['error' => 'Webhook not configured.'], 500);
}

// HMAC SHA-256 signature check. Constant-time compare.
$expected = hash_hmac('sha256', $payload, $webhookSecret);
if (!is_string($signature) || !hash_equals($expected, $signature)) {
    logError('ghl', 'GHL webhook signature verification failed.', null, ['ip' => clientIp()]);
    jsonResponse(['error' => 'Invalid signature.'], 401);
}

$data = json_decode($payload, true);
if (!is_array($data)) {
    jsonResponse(['error' => 'Invalid payload.'], 400);
}

$event = (string) ($data['event'] ?? $data['type'] ?? '');
$email = (string) ($data['email'] ?? $data['contact']['email'] ?? $data['contact_email'] ?? '');
$contactId = (string) ($data['contactId'] ?? $data['contact_id'] ?? $data['contact']['id'] ?? '');

// Normalize nested common fields for handlers.
$productId = (string) (
    $data['product_id']
    ?? $data['productId']
    ?? $data['offer_id']
    ?? $data['offerId']
    ?? $data['subscription']['product_id']
    ?? ''
);
$currentPeriodEnd = (string) (
    $data['current_period_end']
    ?? $data['subscription']['current_period_end']
    ?? ''
);

if ($email === '') {
    logError('ghl', "GHL webhook event '{$event}' without an email.", null, $data);
    jsonResponse(['received' => true, 'ignored' => 'missing_email'], 200);
}

$ctx = [
    'email'              => $email,
    'contact_id'         => $contactId,
    'product_id'         => $productId,
    'current_period_end' => $currentPeriodEnd,
    'raw'                => $data,
];

$pdo = getDB();

try {
    switch ($event) {
        case 'subscription.created':
        case 'subscription_created':
            handleGHLSubscriptionCreated($pdo, $ctx);
            break;

        case 'subscription.renewed':
        case 'subscription_renewed':
            handleGHLRenewal($pdo, $ctx);
            break;

        case 'subscription.canceled':
        case 'subscription.cancelled':
        case 'subscription_canceled':
            handleGHLCancellation($pdo, $ctx);
            break;

        case 'subscription.upgraded':
        case 'subscription.downgraded':
        case 'subscription.plan_changed':
        case 'subscription_upgraded':
        case 'subscription_downgraded':
            handleGHLPlanChange($pdo, $ctx);
            break;

        case 'payment.failed':
        case 'payment_failed':
            handleGHLPaymentFailed($pdo, $ctx);
            break;

        default:
            logError('ghl', "Unknown GHL webhook event: {$event}", null, $data);
    }
} catch (Throwable $e) {
    logError('ghl', 'GHL webhook handler crashed: ' . $e->getMessage(), null, $ctx);
    jsonResponse(['error' => 'Handler failure.'], 500);
}

jsonResponse(['received' => true], 200);


// -----------------------------------------------------------
// Handlers
// -----------------------------------------------------------

function handleGHLSubscriptionCreated(PDO $pdo, array $ctx): void
{
    $email     = $ctx['email'];
    $contactId = $ctx['contact_id'];
    $plan      = mapGHLProductToPlan($ctx['product_id']);
    $credits   = (int) getSetting("credits_{$plan}", '5');
    $anchorDay = min((int) date('j'), 28);
    $periodEnd = $ctx['current_period_end'] !== ''
        ? $ctx['current_period_end']
        : date('Y-m-d H:i:s', strtotime('+1 month') ?: time() + 30 * 86400);

    $existing = getUserByEmail($email);

    if ($existing !== null) {
        $userId = (int) $existing['id'];
        $stmt = $pdo->prepare(
            "UPDATE alleogen_users SET
                ghl_contact_id = :gcid,
                plan = :plan,
                subscription_status = 'active',
                subscription_anchor_day = :anchor,
                subscription_started_at = NOW(),
                current_period_end = :period,
                credits_remaining = :credits,
                credits_per_cycle = :credits,
                credits_reset_at = :period,
                delete_clock_active = 0,
                delete_clock_start = NULL,
                delete_files_on = NULL,
                reminder_5day_sent = 0,
                reminder_3day_sent = 0,
                reminder_1day_sent = 0
             WHERE id = :id"
        );
        $stmt->execute([
            ':gcid'    => $contactId !== '' ? $contactId : $existing['ghl_contact_id'],
            ':plan'    => $plan,
            ':anchor'  => $anchorDay,
            ':period'  => $periodEnd,
            ':credits' => $credits,
            ':id'      => $userId,
        ]);
        logSubscriptionEvent($userId, 'reactivated', $existing['plan'] ?? 'none', $plan, $credits, $ctx['raw']);
    } else {
        $tempPassword = bin2hex(random_bytes(8)); // 16-char
        $stmt = $pdo->prepare(
            "INSERT INTO alleogen_users
                (email, password_hash, ghl_contact_id, plan, subscription_status,
                 subscription_anchor_day, subscription_started_at, current_period_end,
                 credits_remaining, credits_per_cycle, credits_reset_at)
             VALUES (:email, :hash, :gcid, :plan, 'active',
                     :anchor, NOW(), :period,
                     :credits, :credits, :period)"
        );
        $stmt->execute([
            ':email'   => $email,
            ':hash'    => password_hash($tempPassword, PASSWORD_BCRYPT),
            ':gcid'    => $contactId,
            ':plan'    => $plan,
            ':anchor'  => $anchorDay,
            ':period'  => $periodEnd,
            ':credits' => $credits,
        ]);
        $userId = (int) $pdo->lastInsertId();

        sendWelcomeEmail($email, $tempPassword, $plan);
        logSubscriptionEvent($userId, 'created', 'none', $plan, $credits, $ctx['raw']);
    }

    // Claim any existing one-timer generations by email, halt their
    // delete clocks, and flag them as subscription-backed.
    $stmt = $pdo->prepare(
        "UPDATE alleogen_generations SET
            user_id = :uid,
            delete_files_on = NULL,
            purchase_type = 'subscription',
            reminder_5day_sent = 0,
            reminder_3day_sent = 0,
            reminder_1day_sent = 0
         WHERE payment_email = :email AND (user_id IS NULL OR user_id = :uid)"
    );
    $stmt->execute([':uid' => $userId, ':email' => $email]);
}

function handleGHLRenewal(PDO $pdo, array $ctx): void
{
    $user = getUserByEmail($ctx['email']);
    if ($user === null) return;

    $periodEnd = $ctx['current_period_end'] !== ''
        ? $ctx['current_period_end']
        : date('Y-m-d H:i:s', strtotime('+1 month') ?: time() + 30 * 86400);

    // Replenish credits (no rollover of unused).
    $stmt = $pdo->prepare(
        "UPDATE alleogen_users SET
            credits_remaining = credits_per_cycle,
            credits_reset_at = :period,
            current_period_end = :period,
            subscription_status = 'active',
            delete_clock_active = 0,
            delete_clock_start = NULL,
            delete_files_on = NULL,
            reminder_5day_sent = 0,
            reminder_3day_sent = 0,
            reminder_1day_sent = 0
         WHERE id = :id"
    );
    $stmt->execute([':period' => $periodEnd, ':id' => $user['id']]);

    logSubscriptionEvent((int) $user['id'], 'renewed', $user['plan'], $user['plan'], (int) $user['credits_per_cycle'], $ctx['raw']);
}

function handleGHLCancellation(PDO $pdo, array $ctx): void
{
    $user = getUserByEmail($ctx['email']);
    if ($user === null) return;

    $periodEnd = $ctx['current_period_end'] !== ''
        ? $ctx['current_period_end']
        : (string) ($user['current_period_end'] ?? date('Y-m-d H:i:s'));

    $storageDays = (int) getSetting('onetime_storage_days', '30');
    $deleteOn = date('Y-m-d H:i:s', strtotime("{$periodEnd} +{$storageDays} days") ?: time() + $storageDays * 86400);

    $stmt = $pdo->prepare(
        "UPDATE alleogen_users SET
            subscription_status = 'canceled',
            delete_clock_active = 1,
            delete_clock_start = :start,
            delete_files_on = :delon,
            reminder_5day_sent = 0,
            reminder_3day_sent = 0,
            reminder_1day_sent = 0
         WHERE id = :id"
    );
    $stmt->execute([':start' => $periodEnd, ':delon' => $deleteOn, ':id' => $user['id']]);

    $stmt = $pdo->prepare(
        "UPDATE alleogen_generations SET
            delete_files_on = :delon,
            reminder_5day_sent = 0,
            reminder_3day_sent = 0,
            reminder_1day_sent = 0
         WHERE user_id = :uid AND purchase_type = 'subscription'"
    );
    $stmt->execute([':delon' => $deleteOn, ':uid' => $user['id']]);

    logSubscriptionEvent((int) $user['id'], 'canceled', $user['plan'], 'none', null, $ctx['raw']);
}

function handleGHLPlanChange(PDO $pdo, array $ctx): void
{
    $user = getUserByEmail($ctx['email']);
    if ($user === null) return;

    $newPlan = mapGHLProductToPlan($ctx['product_id']);
    $newCredits = (int) getSetting("credits_{$newPlan}", '5');
    $oldPlan = (string) $user['plan'];
    $oldCredits = (int) $user['credits_per_cycle'];

    $stmt = $pdo->prepare(
        "UPDATE alleogen_users SET
            plan = :plan,
            credits_per_cycle = :credits,
            subscription_status = 'active',
            delete_clock_active = 0,
            delete_clock_start = NULL,
            delete_files_on = NULL
         WHERE id = :id"
    );
    $stmt->execute([':plan' => $newPlan, ':credits' => $newCredits, ':id' => $user['id']]);

    $eventName = $newCredits > $oldCredits ? 'upgraded' : 'downgraded';
    logSubscriptionEvent((int) $user['id'], $eventName, $oldPlan, $newPlan, null, $ctx['raw']);
}

function handleGHLPaymentFailed(PDO $pdo, array $ctx): void
{
    $user = getUserByEmail($ctx['email']);
    if ($user === null) return;

    $stmt = $pdo->prepare(
        "UPDATE alleogen_users SET subscription_status = 'past_due' WHERE id = :id"
    );
    $stmt->execute([':id' => $user['id']]);

    logSubscriptionEvent((int) $user['id'], 'payment_failed', $user['plan'], $user['plan'], null, $ctx['raw']);
}
