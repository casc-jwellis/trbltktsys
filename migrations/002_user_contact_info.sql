-- Adds email and phone number fields to staff accounts.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database — schema.sql already includes it.)

ALTER TABLE users
    ADD COLUMN email VARCHAR(150) NULL AFTER full_name,
    ADD COLUMN phone VARCHAR(30) NULL AFTER email;
