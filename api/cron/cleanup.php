<?php
/**
 * cron/cleanup.php
 *
 * Runs daily. Enforces storage windows:
 *   - One-time generations past delete_files_on → delete zip + rows.
 *   - Canceled subscribers past delete_files_on → delete all their
 *     generation zips + rows; flip their account back to no plan.
 *   - Orphan analyses (no referring generation) → delete.
 *
 * Schedule (crontab):
 *   0 3 * * * /usr/bin/php /path/to/tools/alleogen/api/cron/cleanup.php >> /var/log/alleogen-cron.log 2>&1
 *
 * CLI only.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

require_once __DIR__ . '/../config.php';

$start = microtime(true);
$pdo   = getDB();

$stats = [
    'onetime_zips_deleted'     => 0,
    'onetime_rows_deleted'     => 0,
    'subscriber_zips_deleted'  => 0,
    'subscriber_rows_deleted'  => 0,
    'users_reset'              => 0,
    'orphan_analyses_deleted'  => 0,
    'zip_missing_on_disk'      => 0,
    'errors'                   => 0,
];

// --- 1) Expired one-time generations: remove file + row ---
$stmt = $pdo->query(
    "SELECT id, zip_filename, analysis_id FROM alleogen_generations
     WHERE purchase_type = 'one_time'
       AND delete_files_on IS NOT NULL
       AND delete_files_on <= NOW()"
);
$expiredOnetime = $stmt->fetchAll();

foreach ($expiredOnetime as $gen) {
    try {
        if (!empty($gen['zip_filename'])) {
            $path = rtrim(STORAGE_PATH, '/') . '/' . basename((string) $gen['zip_filename']);
            if (is_file($path)) {
                if (@unlink($path)) $stats['onetime_zips_deleted']++;
            } else {
                $stats['zip_missing_on_disk']++;
            }
        }
        $del = $pdo->prepare('DELETE FROM alleogen_generations WHERE id = :id');
        $del->execute([':id' => $gen['id']]);
        $stats['onetime_rows_deleted']++;
    } catch (Throwable $e) {
        $stats['errors']++;
        logError('api', 'cleanup one-time row failed: ' . $e->getMessage(), null, ['generation_id' => $gen['id']]);
    }
}

// --- 2) Canceled subscribers past their deletion window ---
$stmt = $pdo->query(
    "SELECT id FROM alleogen_users
     WHERE delete_clock_active = 1
       AND delete_files_on IS NOT NULL
       AND delete_files_on <= NOW()"
);
$expiredUsers = $stmt->fetchAll();

foreach ($expiredUsers as $user) {
    $uid = (int) $user['id'];
    try {
        // Delete each generation's zip from disk, then remove the rows.
        $gens = $pdo->prepare('SELECT id, zip_filename FROM alleogen_generations WHERE user_id = :uid');
        $gens->execute([':uid' => $uid]);
        foreach ($gens as $g) {
            if (!empty($g['zip_filename'])) {
                $path = rtrim(STORAGE_PATH, '/') . '/' . basename((string) $g['zip_filename']);
                if (is_file($path)) {
                    if (@unlink($path)) $stats['subscriber_zips_deleted']++;
                } else {
                    $stats['zip_missing_on_disk']++;
                }
            }
            $stats['subscriber_rows_deleted']++;
        }

        $del = $pdo->prepare('DELETE FROM alleogen_generations WHERE user_id = :uid');
        $del->execute([':uid' => $uid]);

        // Reset the user account back to "no plan" state but keep the row
        // so they can log in later and re-subscribe.
        $upd = $pdo->prepare(
            "UPDATE alleogen_users SET
                plan = 'none',
                subscription_status = 'canceled',
                credits_remaining = 0,
                credits_per_cycle = 0,
                credits_reset_at = NULL,
                delete_clock_active = 0,
                delete_clock_start = NULL,
                delete_files_on = NULL,
                reminder_5day_sent = 0,
                reminder_3day_sent = 0,
                reminder_1day_sent = 0
             WHERE id = :uid"
        );
        $upd->execute([':uid' => $uid]);
        $stats['users_reset']++;

        logSubscriptionEvent($uid, 'canceled', null, 'none', null, ['reason' => 'storage_window_expired']);
    } catch (Throwable $e) {
        $stats['errors']++;
        logError('api', 'cleanup subscriber failed: ' . $e->getMessage(), null, ['user_id' => $uid]);
    }
}

// --- 3) Orphan analyses (no referring generation, older than a day) ---
try {
    $del = $pdo->prepare(
        "DELETE a FROM alleogen_analyses a
         LEFT JOIN alleogen_generations g ON g.analysis_id = a.id
         WHERE g.id IS NULL
           AND a.created_at < DATE_SUB(NOW(), INTERVAL 1 DAY)"
    );
    $del->execute();
    $stats['orphan_analyses_deleted'] = $del->rowCount();
} catch (Throwable $e) {
    $stats['errors']++;
    logError('api', 'cleanup orphan analyses failed: ' . $e->getMessage());
}

// --- 4) Orphan zip files on disk (zip_filename doesn't exist in DB) ---
$orphanZips = 0;
if (is_dir(STORAGE_PATH)) {
    $known = [];
    $stmt = $pdo->query("SELECT zip_filename FROM alleogen_generations WHERE zip_filename IS NOT NULL");
    foreach ($stmt as $row) {
        $known[basename((string) $row['zip_filename'])] = true;
    }
    foreach (glob(rtrim(STORAGE_PATH, '/') . '/*.zip') ?: [] as $path) {
        $name = basename($path);
        if ($name === '' || isset($known[$name])) continue;
        // Only sweep zips older than 48h to avoid racing an in-flight generation.
        if (filemtime($path) < time() - 2 * 86400) {
            if (@unlink($path)) $orphanZips++;
        }
    }
}
$stats['orphan_zips_deleted'] = $orphanZips;

$elapsed = number_format((microtime(true) - $start) * 1000, 0);
fprintf(
    STDOUT,
    "[%s] cleanup: %s elapsed=%sms\n",
    date('c'),
    http_build_query($stats, '', ' '),
    $elapsed
);
