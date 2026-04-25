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
 *
 * Delivery path:
 *   - If config.local.php has SMTP secrets (`smtp_host`), use PHPMailer
 *     to relay through that server. This is the production-grade path.
 *     Works with Resend, Postmark, SendGrid, AWS SES, Mailgun, or any
 *     vanilla SMTP relay.
 *   - Otherwise fall back to PHP's mail() — fine for dev and for hosts
 *     where Exim is trustworthy, but typically unreliable on shared
 *     hosting due to sending-reputation issues.
 */
function sendMail(string $toEmail, string $subject, string $body): bool
{
    if (filter_var($toEmail, FILTER_VALIDATE_EMAIL) === false) {
        logError('api', "sendMail rejected invalid recipient: $toEmail");
        return false;
    }

    // Strip CR/LF from subject to defend against header injection.
    $safeSubject = preg_replace('/[\r\n]+/', ' ', $subject) ?? '';

    $fromAddress = preg_replace('/[\r\n]+/', '', getSetting('mail_from_address', 'no-reply@cherisanerd.com')) ?? '';
    $fromName    = preg_replace('/[\r\n]+/', ' ', getSetting('mail_from_name',    'AEO File Generator')) ?? '';

    $smtpHost = secret('smtp_host');
    if ($smtpHost !== '') {
        return sendMailViaSmtp($toEmail, $safeSubject, $body, $fromAddress, $fromName);
    }
    return sendMailViaMailFn($toEmail, $safeSubject, $body, $fromAddress, $fromName);
}

/**
 * PHPMailer-backed SMTP delivery. Reads credentials from
 * config.local.php:
 *
 *   'smtp_host'     => 'smtp.resend.com'
 *   'smtp_port'     => 465                  (default 587)
 *   'smtp_encryption' => 'ssl' | 'tls'      (default tls)
 *   'smtp_user'     => 'resend'
 *   'smtp_pass'     => 're_your_api_key_here'
 */
function sendMailViaSmtp(string $toEmail, string $subject, string $body, string $fromAddress, string $fromName): bool
{
    // Lazy-load PHPMailer so we don't pay the autoload cost on
    // mail()-only deploys.
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        $autoload = __DIR__ . '/../vendor/autoload.php';
        if (is_file($autoload)) require_once $autoload;
    }
    if (!class_exists(\PHPMailer\PHPMailer\PHPMailer::class)) {
        logError('api', 'PHPMailer autoload missing; falling back to mail().');
        return sendMailViaMailFn($toEmail, $subject, $body, $fromAddress, $fromName);
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = secret('smtp_host');
        $mail->Port       = (int) (secret('smtp_port', '587') ?: 587);
        $mail->SMTPAuth   = true;
        $mail->Username   = secret('smtp_user');
        $mail->Password   = secret('smtp_pass');
        $enc = strtolower(secret('smtp_encryption', 'tls'));
        $mail->SMTPSecure = $enc === 'ssl'
            ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;

        $mail->CharSet = 'UTF-8';
        $mail->setFrom($fromAddress, $fromName);
        $mail->addAddress($toEmail);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->isHTML(false);

        $mail->send();
        return true;
    } catch (\Throwable $e) {
        logError('api', 'SMTP send failed: ' . $e->getMessage(), null, [
            'host' => secret('smtp_host'),
            'to'   => $toEmail,
        ]);
        return false;
    }
}

/**
 * Original mail() fallback. Kept so dev setups without SMTP creds
 * still work, and so deploys that don't need a relay can skip PHPMailer.
 */
function sendMailViaMailFn(string $toEmail, string $subject, string $body, string $fromAddress, string $fromName): bool
{
    $headers = [
        "From: {$fromName} <{$fromAddress}>",
        "Reply-To: {$fromAddress}",
        "MIME-Version: 1.0",
        "Content-Type: text/plain; charset=utf-8",
        "X-Mailer: alleogen/" . (defined('ALLEOGEN_VERSION') ? ALLEOGEN_VERSION : 'dev'),
    ];

    $ok = @mail($toEmail, $subject, $body, implode("\r\n", $headers));
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
 * Password reset email. The raw token is embedded in the link —
 * the DB only stores its SHA-256 hash.
 */
function sendPasswordResetEmail(string $email, string $resetUrl): bool
{
    $subject = 'Reset your AI Visibility Generator password';
    $body = <<<TXT
A password reset was requested for this email address.

If you requested it, click the link below within the next hour to set
a new password:

{$resetUrl}

If you did not request a reset, you can safely ignore this email —
your current password will continue to work.

This link is single-use and expires in 60 minutes.
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
