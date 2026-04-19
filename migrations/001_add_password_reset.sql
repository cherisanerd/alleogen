-- Migration: add password-reset columns to alleogen_users.
-- Run on any database that was created with an earlier schema.sql
-- (fresh installs pick these up automatically from schema.sql).
--
-- Apply via phpMyAdmin → SQL tab, or:
--   mysql -u DB_USER -p DB_NAME < migrations/001_add_password_reset.sql

ALTER TABLE alleogen_users
    ADD COLUMN reset_token_hash    VARCHAR(64) DEFAULT NULL AFTER last_login_at,
    ADD COLUMN reset_token_expires DATETIME    DEFAULT NULL AFTER reset_token_hash,
    ADD INDEX idx_reset_token (reset_token_hash);
