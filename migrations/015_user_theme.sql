-- Adds a per-agent theme preference (light/dark) so the dark-mode toggle
-- follows a logged-in agent to another browser/device, not just the
-- localStorage it also writes to (which is all an anonymous ticket
-- submitter has, since they have no account to persist it against).
-- NULL means no preference set yet -- follow the OS/browser default.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

ALTER TABLE users ADD COLUMN theme VARCHAR(10) NULL AFTER disabled;
