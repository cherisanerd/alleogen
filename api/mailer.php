<?php
/**
 * Very thin mail() wrapper. Subject + headers are sanitized to prevent
 * header injection. For higher deliverability in production, swap mail()
 * for PHPMailer/SMTP here — all callers go through sendMail().
 */

declare(strict_types=1);

/**
 * Send a plain-text email. Returns true on success, false on failure.
 * Failures are logged but never throw.
 */
function sendMail(string $toEmail, string $subject, string $body): bool
{
    if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
        logError('api', "sendMail rejected invalid recipient: $toEmail");
        return false;
    }

    // Strip CR/LF from subject to defend against header injection.
    $safeSubject = preg_replace('/[\r\n]+/', ' ', $subject) ?? '';

    $fromAddress = getSetting('mail_from_address', 'no-reply@cherisanerd.com');
    $fromName    = getSetting('mail_from_name', 'AEO File Generator');

    // Also scrub CR/LF from the From header source.
    $fromName    = preg_replace('/[\r\n]+/', ' ', $fromName) ?? '';
    $fromAddress = preg_replace('/[\r\n]+/', '',  $fromAddress) ?? '';

    $headers = [
        "From: {$fromName} <{$fromAddress}>",
        "Reply-To: {$fromAddress}",
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=utf-8",
        "X-Mailer: alleogen/" . (defined('ALLEOGEN_VERSION') ? ALLEOGEN_VERSION : 'dev'),
    ];

    $ok = @mail($toEmail, $safeSubject, $body, implode("\r\n", $headers));
    if (!$ok) {
        logError('api', "mail() failed to $toEmail");
    }
    return (bool) $ok;
}

/**
 * Email sent after a successful one-time Stripe purchase.
 */
function sendOneTimeAccessEmail(string $email, string $accessUrl, string $tier, string $deleteOn): bool
{
    $tierLabel  = $tier === 'complete' ? 'Complete' : 'Basic';
    $deleteDate = date('F j, Y', strtotime($deleteOn) ?: time());
    $subject = "Your AI Visibility Package — {$tierLabel} is Ready";
    $body = <<<TXT
Your {$tierLabel} Package access is ready.

Click below to start your AI visibility questionnaire:
{$accessUrl}

This link is your personal access key. Bookmark it.

IMPORTANT: Your files will be available until {$deleteDate}.
After that date, they will be permanently deleted.
We will send reminders at 5, 3, and 1 day before deletion.

Want permanent storage? Upgrade to a monthly plan:

TXT;
    $body .= APP_URL . "/pricing\n";
    return sendMail($email, $subject, $body);
}

/**
 * Welcome email sent after a GHL subscription webhook creates an account.
 */
function sendWelcomeEmail(string $email, string $tempPassword, string $plan): bool
{
    $planLabel = $plan === 'agency' ? 'Agency' : 'Pro';
    $credits   = getSetting("credits_{$plan}", '5');
    $loginUrl  = APP_URL . '/login';
    $subject   = "Your AI Visibility Generator — {$planLabel} account is ready";
    $body = <<<TXT
Welcome to the AI Visibility Generator.

Your {$planLabel} subscription is active with {$credits} credits per month.

Log in to your dashboard:
{$loginUrl}

Email: {$email}
Temporary password: {$tempPassword}

Please change your password after your first login.

TXT;
    return sendMail($email, $subject, $body);
}

/**
 * Generic deletion reminder. Used by the cron job.
 */
function sendDeletionReminder(string $email, string $targetLabel, int $daysLeft, string $accessUrl): bool
{
    $subject = "Reminder: Your AI Visibility files will be deleted in {$daysLeft} day" . ($daysLeft === 1 ? '' : 's');
    $body = <<<TXT
Your files for {$targetLabel} will be deleted in {$daysLeft} day.

Access them here:
{$accessUrl}

Want permanent storage? Upgrade to a monthly plan:

TXT;
    $body .= APP_URL . "/pricing\n";
    return sendMail($email, $subject, $body);
}
