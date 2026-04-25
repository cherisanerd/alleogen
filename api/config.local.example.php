<?php
/**
 * AEO File Generator — LOCAL SECRETS TEMPLATE.
 *
 * Copy this file to config.local.php on each deploy and fill in real values.
 * config.local.php is gitignored and MUST NOT be committed.
 *
 * The web server must not serve this file. The top-level api/.htaccess
 * already denies direct access to any config*.php file, but keep this file
 * outside the document root if you can.
 */

declare(strict_types=1);

return [
    // Public app URL (no trailing slash).
    'app_url' => 'https://cherisanerd.com/tools/alleogen',

    // 'production' | 'staging' | 'development'. Controls verbose errors.
    'app_env' => 'production',

    // Absolute path to the directory that stores generated zip files.
    // Default: one level above /api, in /storage/zips. Must be writable.
    // For maximum safety, place this OUTSIDE the web-served document root.
    'storage_path' => dirname(__DIR__) . '/storage/zips',

    'db' => [
        'host'   => '127.0.0.1',
        'port'   => 3306,
        'name'   => 'cherisanerd_alleogen',
        'user'   => 'alleogen_app',
        'pass'   => 'REPLACE_WITH_DB_PASSWORD',
        // Optional: set to a unix socket path to use the socket instead.
        'socket' => '',
    ],

    'secrets' => [
        // Stripe — one-time payments only.
        'stripe_secret_key'     => '',
        'stripe_webhook_secret' => '',

        // GoHighLevel — subscription management.
        'ghl_api_key'           => '',
        'ghl_location_id'       => '',
        'ghl_webhook_secret'    => '',

        // First admin email — any user account with this email is auto-admin
        // on first login. Leave blank to disable bootstrap.
        'bootstrap_admin_email' => '',

        // SMTP relay — optional. If 'smtp_host' is non-empty, the mailer
        // uses PHPMailer over SMTP. Otherwise it falls back to PHP's
        // mail() function. Recommended: Resend, Postmark, SendGrid,
        // AWS SES, Mailgun.
        //
        // Resend example (free tier — 3,000 emails/month):
        //   'smtp_host'       => 'smtp.resend.com',
        //   'smtp_port'       => 465,
        //   'smtp_encryption' => 'ssl',
        //   'smtp_user'       => 'resend',
        //   'smtp_pass'       => 're_your_api_key_here',
        //
        // Postmark:
        //   'smtp_host'       => 'smtp.postmarkapp.com',
        //   'smtp_port'       => 587,
        //   'smtp_encryption' => 'tls',
        //   'smtp_user'       => 'your-server-token',
        //   'smtp_pass'       => 'your-server-token',
        'smtp_host'       => '',
        'smtp_port'       => '',
        'smtp_encryption' => '',
        'smtp_user'       => '',
        'smtp_pass'       => '',
    ],
];
