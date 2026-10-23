-- tickets.category was VARCHAR(30) but categories.name (Admin Settings ->
-- Categories) allows names up to 50 characters, so a ticket in any category
-- named 31-50 characters failed to insert ("Data too long") and the ticket
-- form returned a 500. Widens it to match. Only ever widens, so no existing
-- value is touched.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

SET foreign_key_checks = 0;

ALTER TABLE tickets MODIFY category VARCHAR(50) NOT NULL DEFAULT 'General';

SET foreign_key_checks = 1;
