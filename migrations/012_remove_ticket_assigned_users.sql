-- Removes per-user ticket assignment -- tickets are now assignable to
-- groups only, matching the group-only category permissions from the
-- previous migration.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

DROP TABLE ticket_assigned_users;
