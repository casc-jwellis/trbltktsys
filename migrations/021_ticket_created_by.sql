-- Records which agent opened a ticket on a requester's behalf (create-ticket.php).
-- NULL means the requester submitted it themselves via submit-ticket.php or
-- by email. Shown on the ticket page and used to word the requester's
-- confirmation email -- see includes/functions.php and includes/mail.php.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

ALTER TABLE tickets
    ADD COLUMN created_by_user_id INT UNSIGNED NULL AFTER assigned_agent_id,
    ADD CONSTRAINT fk_tickets_created_by FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
