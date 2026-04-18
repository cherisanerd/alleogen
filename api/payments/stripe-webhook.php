<?php
/**
 * POST /api/payments/stripe-webhook
 *
 * Stripe calls this endpoint after checkout. We verify the signature,
 * then activate the generation row and email the access link.
 *
 * Events handled:
 *   - checkout.session.completed → activate generation, send access email
 *   - payment_intent.payment_failed → mark generation failed
 *
 * No auth middleware — Stripe signature IS the auth.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../../vendor/autoload.php';

requireMethod('POST');

$stripeKey = secret('stripe_secret_key');
$webhookSecret = secret('stripe_webhook_secret');
if ($stripeKey === '' || $webhookSecret === '') {
    logError('billing', 'Stripe webhook hit without configured keys.');
    jsonResponse(['error' => 'Webhook not configured.'], 500);
}

\Stripe\Stripe::setApiKey($stripeKey);

$payload = file_get_contents('php://input') ?: '';
$sigHeader = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

try {
    $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
} catch (\UnexpectedValueException $e) {
    logError('billing', 'Invalid Stripe webhook payload: ' . $e->getMessage());
    jsonResponse(['error' => 'Invalid payload.'], 400);
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    logError('billing', 'Invalid Stripe webhook signature: ' . $e->getMessage());
    jsonResponse(['error' => 'Invalid signature.'], 401);
}

$pdo = getDB();

switch ($event->type) {
    case 'checkout.session.completed':
        handleCheckoutCompleted($pdo, $event->data->object);
        break;

    case 'payment_intent.payment_failed':
        handlePaymentFailed($pdo, $event->data->object);
        break;

    default:
        // Unhandled events — ack so Stripe stops retrying.
        break;
}

jsonResponse(['received' => true], 200);

/**
 * @param \Stripe\Checkout\Session $session
 */
function handleCheckoutCompleted(PDO $pdo, $session): void
{
    $meta = $session->metadata ?? null;
    if (!$meta) return;

    $generationId = isset($meta->generation_id) ? (int) $meta->generation_id : 0;
    $accessToken  = (string) ($meta->access_token ?? '');
    $tier         = (string) ($meta->package_tier ?? 'basic');

    if ($generationId === 0) {
        logError('billing', 'checkout.session.completed missing generation_id', null, ['session_id' => $session->id ?? null]);
        return;
    }

    $paymentId = (string) ($session->payment_intent ?? $session->id ?? '');
    $email     = (string) ($session->customer_email ?? $session->customer_details->email ?? '');

    $storageDays = (int) getSetting('onetime_storage_days', '30');
    $deleteOn    = date('Y-m-d H:i:s', strtotime("+{$storageDays} days") ?: time() + $storageDays * 86400);

    $stmt = $pdo->prepare(
        "UPDATE alleogen_generations SET
            status = 'questionnaire_incomplete',
            payment_id = :pid,
            payment_email = :email,
            delete_files_on = :delon
         WHERE id = :id AND status = 'pending_payment'"
    );
    $stmt->execute([
        ':pid'   => $paymentId,
        ':email' => $email !== '' ? $email : null,
        ':delon' => $deleteOn,
        ':id'    => $generationId,
    ]);

    if ($stmt->rowCount() === 0) {
        // Either already processed or row missing — either way, ack.
        return;
    }

    if ($email !== '') {
        $accessUrl = APP_URL . '/g/' . $accessToken;
        sendOneTimeAccessEmail($email, $accessUrl, $tier, $deleteOn);
    }
}

/**
 * @param \Stripe\PaymentIntent $pi
 */
function handlePaymentFailed(PDO $pdo, $pi): void
{
    $paymentId = (string) ($pi->id ?? '');
    if ($paymentId === '') return;

    $stmt = $pdo->prepare(
        "UPDATE alleogen_generations SET status = 'failed'
         WHERE payment_id = :pid AND status IN ('pending_payment', 'questionnaire_incomplete')"
    );
    $stmt->execute([':pid' => $paymentId]);
}
