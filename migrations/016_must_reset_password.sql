-- Adds a per-user flag forcing a password reset on next login. Set when an
-- admin creates a new account (see admin-settings.php) since the password is
-- now randomly generated rather than chosen by the admin. Not backfilled --
-- only newly created accounts require the reset, existing ones are
-- untouched.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

ALTER TABLE users ADD COLUMN must_reset_password TINYINT(1) NOT NULL DEFAULT 0 AFTER theme;
