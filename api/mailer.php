<?php
/**
 * Mail wrapper. Uses PHPMailer over SMTP when 'smtp.host' is configured in
 * config.local.php; falls back to PHP mail() only if SMTP is not set up.
 * All callers go through sendMail(); subject + addresses are sanitized to
 * prevent header injection.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

require_once dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Send a plain-text email. Returns true on success, false on failure.
 * Failures are logged with the underlying SMTP error but never throw.
 */
function sendMail(string $toEmail, string $subject, string $body): bool
{
    if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
        logError('api', "sendMail rejected invalid recipient: $toEmail");
        return false;
    }

    $safeSubject = preg_replace('/[\r\n]+/', ' ', $subject) ?? '';

    $fromAddress = (string) getSetting('mail_from_address', 'no-reply@cherisanerd.com');
    $fromName    = (string) getSetting('mail_from_name', 'AEO File Generator');
    $fromName    = preg_replace('/[\r\n]+/', ' ', $fromName) ?? '';
    $fromAddress = preg_replace('/[\r\n]+/', '',  $fromAddress) ?? '';

    global $LOCAL_CONFIG;
    $smtp = is_array($LOCAL_CONFIG['smtp'] ?? null) ? $LOCAL_CONFIG['smtp'] : [];
    $host = (string) ($smtp['host'] ?? '');

    if ($host === '') {
        // No SMTP configured — fall back to PHP mail().
        $headers = [
            "From: {$fromName} <{$fromAddress}>",
            "Reply-To: {$fromAddress}",
            "MIME-Version: 1.0",
            "Content-Type: text/plain; charset=utf-8",
            "X-Mailer: alleogen/" . (defined('ALLEOGEN_VERSION') ? ALLEOGEN_VERSION : 'dev'),
        ];
        $ok = @mail($toEmail, $safeSubject, $body, implode("\r\n", $headers));
        if (!$ok) {
            logError('api', "mail() failed to {$toEmail} (no SMTP configured)");
        }
        return (bool) $ok;
    }

    $mailer = new PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host       = $host;
        $mailer->Port       = (int) ($smtp['port'] ?? 465);
        $mailer->SMTPAuth   = true;
        $mailer->Username   = (string) ($smtp['username'] ?? '');
        $mailer->Password   = (string) ($smtp['password'] ?? '');
        $encryption         = strtolower((string) ($smtp['encryption'] ?? 'ssl'));
        $mailer->SMTPSecure = $encryption === 'tls'
            ? PHPMailer::ENCRYPTION_STARTTLS
            : PHPMailer::ENCRYPTION_SMTPS;
        $mailer->CharSet    = 'UTF-8';
        $mailer->XMailer    = 'alleogen/' . (defined('ALLEOGEN_VERSION') ? ALLEOGEN_VERSION : 'dev');

        $mailer->setFrom($fromAddress, $fromName);
        $mailer->addReplyTo($fromAddress, $fromName);
        $mailer->addAddress($toEmail);

        $mailer->Subject = $safeSubject;
        $mailer->Body    = $body;

        $mailer->send();
        return true;
    } catch (PHPMailerException $e) {
        // PHPMailer's ErrorInfo is the user-facing reason (auth failed,
        // host unreachable, etc.) and is safe to log.
        logError('api', "SMTP send failed to {$toEmail}: " . $mailer->ErrorInfo);
        return false;
    } catch (Throwable $e) {
        logError('api', "SMTP send threw for {$toEmail}: " . $e->getMessage());
        return false;
    }
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
