-- Lets a ticket be assigned to a single agent, in addition to (or instead of)
-- a group. Once set, ticket_assigned_agent_emails() notifies only that agent
-- rather than every member of the ticket's assigned groups -- see
-- includes/functions.php and includes/mail.php.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

ALTER TABLE tickets
    ADD COLUMN assigned_agent_id INT UNSIGNED NULL AFTER attachment_path,
    ADD CONSTRAINT fk_tickets_assigned_agent FOREIGN KEY (assigned_agent_id) REFERENCES users(id) ON DELETE SET NULL;

CREATE INDEX idx_tickets_assigned_agent ON tickets(assigned_agent_id);
