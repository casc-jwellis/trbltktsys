-- Replaces the roles / user_roles tables with a single is_admin flag on
-- users. There's no separate "Helpdesk Agent" role any more — every staff
-- account is implicitly an agent, and Administrator is just a flag.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database — schema.sql already includes it.)

ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0 AFTER phone;

UPDATE users u
INNER JOIN user_roles ur ON ur.user_id = u.id
INNER JOIN roles r ON r.id = ur.role_id AND r.name = 'Administrator'
SET u.is_admin = 1;

DROP TABLE user_roles;
DROP TABLE roles;
