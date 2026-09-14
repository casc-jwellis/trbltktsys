-- Adds a requesters directory (keyed by email) for tracking ticket submitters
-- across submissions — name, phone, and when they were last seen.
-- Safe to run against a database created before this feature existed.
-- (Skip this file entirely on a brand-new database — schema.sql already includes it.)

CREATE TABLE IF NOT EXISTS requesters (
    email      VARCHAR(150) NOT NULL PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    phone      VARCHAR(30) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill one row per distinct requester already in tickets, using the name
-- from their most recent submission.
INSERT IGNORE INTO requesters (email, name)
SELECT t.requester_email, t.requester_name
FROM tickets t
INNER JOIN (
    SELECT requester_email, MAX(created_at) AS latest_created_at
    FROM tickets
    GROUP BY requester_email
) latest ON latest.requester_email = t.requester_email AND latest.latest_created_at = t.created_at;

ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_requester FOREIGN KEY (requester_email) REFERENCES requesters(email) ON UPDATE CASCADE;
