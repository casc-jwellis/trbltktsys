-- Adds screenshot-attachment support to tickets.
-- Safe to run against a database created before this feature existed.
-- (Skip this file entirely on a brand-new database — schema.sql already includes it.)

ALTER TABLE tickets ADD COLUMN attachment_path VARCHAR(255) NULL AFTER internal_notes;
