-- tickets.category was VARCHAR(30) but categories.name (Admin Settings ->
-- Categories) allows names up to 50 characters, so a ticket in any category
-- named 31-50 characters failed to insert ("Data too long") and the ticket
-- form returned a 500. Widens it to match. Only ever widens, so no existing
-- value is touched.
--
-- MySQL refuses to change a column while a foreign key is attached to it
-- (error 1832, even with foreign_key_checks off), so the key is dropped,
-- the column widened, and the identical key added back (same definition as
-- migration 005 and schema.sql). Re-adding it re-checks every existing
-- ticket's category against the categories table.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

ALTER TABLE tickets DROP FOREIGN KEY fk_tickets_category;

ALTER TABLE tickets MODIFY category VARCHAR(50) NOT NULL DEFAULT 'General';

ALTER TABLE tickets ADD CONSTRAINT fk_tickets_category FOREIGN KEY (category) REFERENCES categories(name) ON UPDATE CASCADE;
