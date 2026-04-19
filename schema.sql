-- AEO File Generator — MySQL schema (v3.0 migration)
-- Apply with: mysql -u root -p DATABASE_NAME < schema.sql
--
-- All tables prefixed `alleogen_` so they can coexist with other
-- cherisanerd.com tables in the same database.
--
-- Secrets (Stripe keys, GHL API key, DB password, etc.) are NOT stored
-- here — they live in api/config.local.php (gitignored). Only non-secret
-- admin-editable values (prices, credits, storage days, GHL product IDs,
-- public URLs, email from-address) are kept in alleogen_settings.

SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------
-- Users — subscribers (email/password). One-timers don't get
-- rows here; they use access_token links on generations.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS alleogen_users (
    id                      INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email                   VARCHAR(255) NOT NULL UNIQUE,
    password_hash           VARCHAR(255) NOT NULL,
    name                    VARCHAR(255) DEFAULT NULL,
    is_admin                TINYINT(1) DEFAULT 0,

    -- GHL sync
    ghl_contact_id          VARCHAR(255) DEFAULT NULL,

    -- Subscription state (mirrored from GHL via webhooks)
    plan                    ENUM('none', 'pro', 'agency') DEFAULT 'none',
    subscription_status     ENUM('none', 'active', 'past_due', 'canceling', 'canceled') DEFAULT 'none',
    subscription_anchor_day TINYINT DEFAULT NULL,
    subscription_started_at DATETIME DEFAULT NULL,
    current_period_end      DATETIME DEFAULT NULL,

    -- Credits
    credits_remaining       INT DEFAULT 0,
    credits_per_cycle       INT DEFAULT 0,
    credits_reset_at        DATETIME DEFAULT NULL,

    -- Deletion clock (activated on cancellation)
    delete_clock_active     TINYINT(1) DEFAULT 0,
    delete_clock_start      DATETIME DEFAULT NULL,
    delete_files_on         DATETIME DEFAULT NULL,
    reminder_5day_sent      TINYINT(1) DEFAULT 0,
    reminder_3day_sent      TINYINT(1) DEFAULT 0,
    reminder_1day_sent      TINYINT(1) DEFAULT 0,

    coupon_used             TINYINT(1) DEFAULT 0,
    created_at              DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at              DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at           DATETIME DEFAULT NULL,

    -- Password reset flow. token_hash stores SHA-256 of the raw token
    -- emailed to the user; the raw token never touches the DB.
    reset_token_hash        VARCHAR(64) DEFAULT NULL,
    reset_token_expires     DATETIME    DEFAULT NULL,

    INDEX idx_email (email),
    INDEX idx_ghl_contact (ghl_contact_id),
    INDEX idx_subscription_status (subscription_status),
    INDEX idx_delete_clock (delete_clock_active, delete_files_on),
    INDEX idx_reset_token (reset_token_hash)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Analyses — scraper output for a website
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS alleogen_analyses (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED DEFAULT NULL,
    access_token    VARCHAR(64) NOT NULL UNIQUE,
    url             VARCHAR(2048) NOT NULL,
    status          ENUM('analyzing', 'completed', 'failed') DEFAULT 'analyzing',
    platform        VARCHAR(50) DEFAULT NULL,
    page_count      INT DEFAULT NULL,
    extracted_data  JSON DEFAULT NULL,
    site_structure  JSON DEFAULT NULL,
    error_message   TEXT DEFAULT NULL,
    completed_at    DATETIME DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES alleogen_users(id) ON DELETE SET NULL,
    INDEX idx_access_token (access_token),
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Generations — a package tied to a website + questionnaire
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS alleogen_generations (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id             INT UNSIGNED DEFAULT NULL,
    analysis_id         INT UNSIGNED DEFAULT NULL,
    access_token        VARCHAR(64) NOT NULL UNIQUE,
    website_url         VARCHAR(2048) NOT NULL,
    business_name       VARCHAR(255) DEFAULT NULL,
    package_tier        ENUM('basic', 'complete') DEFAULT 'basic',
    status              ENUM('pending_payment', 'questionnaire_incomplete', 'generating', 'completed', 'failed') DEFAULT 'pending_payment',
    questionnaire_data  JSON DEFAULT NULL,
    files_data          JSON DEFAULT NULL,
    zip_filename        VARCHAR(255) DEFAULT NULL,
    purchase_type       ENUM('one_time', 'subscription', 'coupon', 'admin') NOT NULL DEFAULT 'one_time',
    payment_method      ENUM('stripe', 'ghl', 'coupon', 'admin', 'credit') DEFAULT NULL,
    payment_id          VARCHAR(255) DEFAULT NULL,
    payment_email       VARCHAR(255) DEFAULT NULL,
    delete_files_on     DATETIME DEFAULT NULL,
    reminder_5day_sent  TINYINT(1) DEFAULT 0,
    reminder_3day_sent  TINYINT(1) DEFAULT 0,
    reminder_1day_sent  TINYINT(1) DEFAULT 0,
    download_count      INT DEFAULT 0,
    downloaded_at       DATETIME DEFAULT NULL,
    generated_at        DATETIME DEFAULT NULL,
    created_at          DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES alleogen_users(id) ON DELETE SET NULL,
    FOREIGN KEY (analysis_id) REFERENCES alleogen_analyses(id) ON DELETE SET NULL,
    INDEX idx_access_token (access_token),
    INDEX idx_user_id (user_id),
    INDEX idx_payment_email (payment_email),
    INDEX idx_status (status),
    INDEX idx_delete_files_on (delete_files_on),
    INDEX idx_purchase_type (purchase_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Subscriptions log — audit trail for GHL-driven events
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS alleogen_subscriptions_log (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    event           ENUM('created', 'renewed', 'upgraded', 'downgraded', 'canceled', 'reactivated', 'payment_failed', 'credits_reset') NOT NULL,
    plan_from       ENUM('none', 'pro', 'agency') DEFAULT NULL,
    plan_to         ENUM('none', 'pro', 'agency') DEFAULT NULL,
    credits_granted INT DEFAULT NULL,
    ghl_event_data  JSON DEFAULT NULL,
    metadata        JSON DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES alleogen_users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_event (event)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Coupons + redemptions
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS alleogen_coupons (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code            VARCHAR(50) NOT NULL UNIQUE,
    coupon_type     ENUM('one_time_generation', 'subscription_trial', 'credits') DEFAULT 'one_time_generation',
    credits         INT DEFAULT 1,
    trial_days      INT DEFAULT NULL,
    max_uses        INT DEFAULT NULL,
    times_used      INT DEFAULT 0,
    is_active       TINYINT(1) DEFAULT 1,
    expires_at      DATETIME DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS alleogen_coupon_redemptions (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    coupon_id       INT UNSIGNED NOT NULL,
    user_id         INT UNSIGNED DEFAULT NULL,
    email           VARCHAR(255) NOT NULL,
    redeemed_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (coupon_id) REFERENCES alleogen_coupons(id),
    FOREIGN KEY (user_id) REFERENCES alleogen_users(id) ON DELETE SET NULL,
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Feedback
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS alleogen_feedback (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED DEFAULT NULL,
    email           VARCHAR(255) DEFAULT NULL,
    feedback_type   VARCHAR(50) DEFAULT 'general',
    message         TEXT NOT NULL,
    rating          TINYINT DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES alleogen_users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Error logs
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS alleogen_error_logs (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source          ENUM('frontend', 'api', 'scraper', 'generator', 'billing', 'ghl') DEFAULT 'api',
    message         TEXT NOT NULL,
    stack_trace     TEXT DEFAULT NULL,
    user_id         INT UNSIGNED DEFAULT NULL,
    request_data    JSON DEFAULT NULL,
    resolved        TINYINT(1) DEFAULT 0,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_source (source),
    INDEX idx_resolved (resolved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------------
-- Admin-configurable settings (non-secret values only).
-- Secrets (Stripe keys, GHL API key, GHL webhook secret, GHL location ID)
-- live in api/config.local.php and are intentionally NOT stored here.
-- -----------------------------------------------------------
CREATE TABLE IF NOT EXISTS alleogen_settings (
    setting_key     VARCHAR(100) PRIMARY KEY,
    setting_value   TEXT NOT NULL,
    label           VARCHAR(255) NOT NULL,
    setting_group   VARCHAR(50) DEFAULT 'general',
    updated_at      DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO alleogen_settings (setting_key, setting_value, label, setting_group) VALUES
-- Pricing (displayed in UI; actual charges happen in Stripe / GHL)
('price_onetime_basic',          '97',                    'One-Time Basic Price ($)',          'pricing'),
('price_onetime_complete',       '147',                   'One-Time Complete Price ($)',       'pricing'),
('price_pro_monthly',            '49',                    'Pro Monthly Price ($)',             'pricing'),
('price_agency_monthly',         '99',                    'Agency Monthly Price ($)',          'pricing'),

-- Credits per plan
('credits_pro',                  '5',                     'Pro Credits Per Month',             'credits'),
('credits_agency',               '15',                    'Agency Credits Per Month',          'credits'),

-- Storage + reminders
('onetime_storage_days',         '30',                    'One-Time Storage Window (days)',    'storage'),
('reminder_days',                '5,3,1',                 'Reminder Days Before Deletion',     'storage'),

-- Stripe price IDs (public identifiers, safe to store)
('stripe_price_onetime_basic',   '',                      'Stripe Price ID: One-Time Basic',   'stripe'),
('stripe_price_onetime_complete','',                      'Stripe Price ID: One-Time Complete','stripe'),

-- GHL — public identifiers and URLs only (API key + webhook secret live in config.local.php)
('ghl_product_pro',              '',                      'GHL Product/Offer ID: Pro',         'ghl'),
('ghl_product_agency',           '',                      'GHL Product/Offer ID: Agency',      'ghl'),
('ghl_cancel_redirect_url',      '',                      'GHL Cancel/Downgrade Page URL',     'ghl'),
('ghl_subscribe_url_pro',        '',                      'GHL Subscribe URL: Pro',            'ghl'),
('ghl_subscribe_url_agency',     '',                      'GHL Subscribe URL: Agency',         'ghl'),

-- Email
('mail_from_address',            'tools@cherisanerd.com', 'From Email Address',                'email'),
('mail_from_name',               'cherisanerd tools',     'From Name',                         'email');

SET FOREIGN_KEY_CHECKS = 1;
