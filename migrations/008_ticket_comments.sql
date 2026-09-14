-- Replaces the single tickets.internal_notes field with a proper
-- chronological thread of ticket comments -- agent responses meant for the
-- submitter, and internal notes meant only for other staff.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

CREATE TABLE ticket_comments (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id   INT UNSIGNED NOT NULL,
    user_id     INT UNSIGNED NULL,
    body        TEXT NOT NULL,
    is_internal TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_comments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_comments_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_ticket_comments_ticket ON ticket_comments(ticket_id);

-- Existing notes have no recorded author, so they come in as unattributed
-- (user_id NULL) internal notes, timestamped to the ticket's last update.
INSERT INTO ticket_comments (ticket_id, user_id, body, is_internal, created_at)
SELECT id, NULL, internal_notes, 1, updated_at FROM tickets
WHERE internal_notes IS NOT NULL AND internal_notes != '';

ALTER TABLE tickets DROP COLUMN internal_notes;
