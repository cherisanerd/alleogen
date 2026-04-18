<?php
/**
 * cron/send-reminders.php
 *
 * Runs hourly. Sends deletion reminder emails to one-timers and canceled
 * subscribers at configurable intervals before their delete_files_on.
 *
 * Schedule (crontab):
 *   0 * * * * /usr/bin/php /path/to/tools/alleogen/api/cron/send-reminders.php >> /var/log/alleogen-cron.log 2>&1
 *
 * CLI only — returns 403 if invoked over HTTP.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../mailer.php';

$start = microtime(true);
$pdo   = getDB();

$reminderDays = array_values(array_filter(array_map(
    'intval',
    explode(',', getSetting('reminder_days', '5,3,1'))
), fn($n) => $n > 0));

$flagColumn = [
    5 => 'reminder_5day_sent',
    3 => 'reminder_3day_sent',
    1 => 'reminder_1day_sent',
];

$stats = ['onetime_sent' => 0, 'subscriber_sent' => 0, 'skipped' => 0, 'failed' => 0];

// ---- One-time generations ----
$stmt = $pdo->query(
    "SELECT id, access_token, payment_email, business_name, delete_files_on,
            reminder_5day_sent, reminder_3day_sent, reminder_1day_sent
     FROM alleogen_generations
     WHERE purchase_type = 'one_time'
       AND status = 'completed'
       AND zip_filename IS NOT NULL
       AND delete_files_on IS NOT NULL
       AND delete_files_on > NOW()"
);

foreach ($stmt as $gen) {
    $email = (string) ($gen['payment_email'] ?? '');
    if ($email === '') { $stats['skipped']++; continue; }

    $daysLeft = daysUntil((string) $gen['delete_files_on']);
    if ($daysLeft === null) { $stats['skipped']++; continue; }

    $fireOn = findReminderThreshold($daysLeft, $reminderDays);
    if ($fireOn === null) { $stats['skipped']++; continue; }

    $col = $flagColumn[$fireOn] ?? null;
    if ($col === null) { $stats['skipped']++; continue; }

    if (!empty($gen[$col])) { $stats['skipped']++; continue; }

    $label     = (string) ($gen['business_name'] ?: 'your website');
    $accessUrl = APP_URL . '/g/' . $gen['access_token'];

    if (sendDeletionReminder($email, $label, $fireOn, $accessUrl)) {
        $stmt2 = $pdo->prepare(
            "UPDATE alleogen_generations SET {$col} = 1 WHERE id = :id"
        );
        $stmt2->execute([':id' => $gen['id']]);
        $stats['onetime_sent']++;
    } else {
        $stats['failed']++;
    }
}

// ---- Canceled subscribers ----
$stmt = $pdo->query(
    "SELECT id, email, delete_files_on,
            reminder_5day_sent, reminder_3day_sent, reminder_1day_sent
     FROM alleogen_users
     WHERE delete_clock_active = 1
       AND delete_files_on IS NOT NULL
       AND delete_files_on > NOW()"
);

foreach ($stmt as $user) {
    $email = (string) ($user['email'] ?? '');
    if ($email === '') { $stats['skipped']++; continue; }

    $daysLeft = daysUntil((string) $user['delete_files_on']);
    if ($daysLeft === null) { $stats['skipped']++; continue; }

    $fireOn = findReminderThreshold($daysLeft, $reminderDays);
    if ($fireOn === null) { $stats['skipped']++; continue; }

    $col = $flagColumn[$fireOn] ?? null;
    if ($col === null) { $stats['skipped']++; continue; }

    if (!empty($user[$col])) { $stats['skipped']++; continue; }

    $accessUrl = APP_URL . '/dashboard';

    if (sendDeletionReminder($email, 'your account', $fireOn, $accessUrl)) {
        $stmt2 = $pdo->prepare(
            "UPDATE alleogen_users SET {$col} = 1 WHERE id = :id"
        );
        $stmt2->execute([':id' => $user['id']]);
        $stats['subscriber_sent']++;
    } else {
        $stats['failed']++;
    }
}

$elapsed = number_format((microtime(true) - $start) * 1000, 0);
fprintf(
    STDOUT,
    "[%s] send-reminders: onetime=%d subscriber=%d skipped=%d failed=%d elapsed=%sms\n",
    date('c'),
    $stats['onetime_sent'],
    $stats['subscriber_sent'],
    $stats['skipped'],
    $stats['failed'],
    $elapsed
);

// ---- helpers ----
function daysUntil(string $when): ?int
{
    $ts = strtotime($when);
    if ($ts === false) return null;
    $diff = $ts - time();
    if ($diff <= 0) return 0;
    return (int) floor($diff / 86400);
}

/**
 * Given days left and a sorted list of reminder thresholds, pick the
 * highest threshold we've crossed that matches the days-left bucket.
 * Returns the matched threshold or null if not time yet.
 */
function findReminderThreshold(int $daysLeft, array $reminderDays): ?int
{
    rsort($reminderDays);
    foreach ($reminderDays as $threshold) {
        if ($daysLeft <= $threshold) return $threshold;
    }
    return null;
}
