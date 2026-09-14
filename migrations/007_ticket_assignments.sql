-- Replaces the single tickets.assigned_to column with a many-to-many
-- assignment model: a ticket can be assigned to any number of users and/or
-- groups at once. New tickets are seeded from the category's configured
-- users/groups (user_categories / group_categories); helpdesk agents can
-- reassign afterward.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

CREATE TABLE ticket_assigned_users (
    ticket_id INT UNSIGNED NOT NULL,
    user_id   INT UNSIGNED NOT NULL,
    PRIMARY KEY (ticket_id, user_id),
    CONSTRAINT fk_ticket_assigned_users_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_assigned_users_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE ticket_assigned_groups (
    ticket_id INT UNSIGNED NOT NULL,
    group_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (ticket_id, group_id),
    CONSTRAINT fk_ticket_assigned_groups_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_assigned_groups_group FOREIGN KEY (group_id) REFERENCES agent_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO ticket_assigned_users (ticket_id, user_id)
SELECT t.id, u.id FROM tickets t INNER JOIN users u ON u.id = t.assigned_to;

ALTER TABLE tickets DROP FOREIGN KEY fk_tickets_assigned_to;
ALTER TABLE tickets DROP COLUMN assigned_to;
