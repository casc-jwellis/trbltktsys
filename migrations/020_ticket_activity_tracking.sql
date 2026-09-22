-- Powers a shared "New" indicator on a ticket: last_submitter_activity_at is
-- bumped whenever the submitter does something (new ticket, a reply via the
-- web link, or an inbound email reply), and viewed_at is bumped whenever any
-- agent opens the ticket page. A ticket is "New" (see ticket_is_new() in
-- includes/functions.php) whenever the former is more recent than the
-- latter -- shared across all agents, not tracked per agent.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

ALTER TABLE tickets
    ADD COLUMN last_submitter_activity_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER updated_at,
    ADD COLUMN viewed_at TIMESTAMP NULL DEFAULT NULL AFTER last_submitter_activity_at;

-- Existing tickets shouldn't all suddenly show as "New" the moment this
-- migration runs -- mark them as already viewed as of right now.
UPDATE tickets SET viewed_at = CURRENT_TIMESTAMP;
