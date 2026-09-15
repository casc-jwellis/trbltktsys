-- Renames users.is_locked to users.disabled -- same meaning (1 = the
-- account can't log in), just clearer terminology throughout the app.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

ALTER TABLE users CHANGE is_locked disabled TINYINT(1) NOT NULL DEFAULT 0;
