# AEO File Generator — cron jobs

Both scripts in this directory are CLI-only (`exit 403` if invoked over HTTP) and must run on the same host as the PHP API so they can use the same `config.local.php` and `storage/zips/` directory.

## Schedule

Add the following to the app user's crontab (`crontab -e`):

```cron
# Send deletion reminders (5, 3, 1 day thresholds by default)
0 * * * * /usr/bin/php /var/www/cherisanerd.com/tools/alleogen/api/cron/send-reminders.php >> /var/log/alleogen-cron.log 2>&1

# Clean up expired zips + rows
0 3 * * * /usr/bin/php /var/www/cherisanerd.com/tools/alleogen/api/cron/cleanup.php >> /var/log/alleogen-cron.log 2>&1
```

Adjust paths for your deploy location. `/usr/bin/php` → whatever `which php` returns for the app user.

## What each script does

### send-reminders.php (hourly)

- Scans `alleogen_generations` for active one-time packages with `delete_files_on > NOW()`.
- For each row, computes days remaining and picks the matching reminder threshold from the admin setting `reminder_days` (default `5,3,1`).
- Fires `sendDeletionReminder()` via the shared mailer and flips `reminder_{N}day_sent` so the same threshold never re-fires.
- Runs the same logic against `alleogen_users` for canceled subscribers.

### cleanup.php (daily at 3 AM)

1. **Expired one-timers** — deletes the zip from disk and the `alleogen_generations` row outright. The package is gone.
2. **Canceled subscribers past deletion window** — deletes every generation's zip + row, resets the user account back to `plan='none'` + `subscription_status='canceled'` + zero credits. The user row itself stays so they can log in and re-subscribe.
3. **Orphan analyses** — analyses with no referring generation that are >24h old get deleted to keep the table small.
4. **Orphan zip files** — any `.zip` in `storage/zips/` with no matching `zip_filename` in the DB, older than 48h, is swept.

## Testing

Run either script manually at any time:

```bash
php api/cron/send-reminders.php
php api/cron/cleanup.php
```

Both emit one summary line per invocation. Expected output shapes:

```
[2026-04-18T03:00:00+00:00] send-reminders: onetime=3 subscriber=1 skipped=12 failed=0 elapsed=412ms
[2026-04-18T03:00:00+00:00] cleanup: onetime_zips_deleted=2&onetime_rows_deleted=2&subscriber_zips_deleted=0&subscriber_rows_deleted=0&users_reset=0&orphan_analyses_deleted=1&zip_missing_on_disk=0&errors=0&orphan_zips_deleted=0 elapsed=87ms
```

## Safety

- Both scripts `exit` with 403 if invoked via HTTP. The `api/cron/.htaccess` also denies any web access to this directory.
- All writes are prepared statements against primary keys — no cascading DELETEs that could sweep unrelated data.
- `errors` counter in the cleanup summary indicates any exceptions that fired per-row; the script continues with the rest even if one row fails.
