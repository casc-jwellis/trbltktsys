-- Adds a per-ticket public access token so submitters can be emailed a link
-- to view status/responses and post additional replies without logging in
-- (see ticket-status.php). Nullable and not backfilled -- existing tickets
-- simply have no link, since nobody was ever emailed one for them.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

ALTER TABLE tickets ADD COLUMN public_token CHAR(64) NULL AFTER attachment_path;
ALTER TABLE tickets ADD UNIQUE KEY uq_tickets_public_token (public_token);
