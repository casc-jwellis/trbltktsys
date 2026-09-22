-- Files attached to a ticket comment -- currently only populated by inbound
-- mail (bin/imap-poll.php), which can carry any number of attachments per
-- reply, unlike the single tickets.attachment_path screenshot captured at
-- submission time.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

CREATE TABLE ticket_comment_attachments (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    comment_id        INT UNSIGNED NOT NULL,
    path              VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NULL,
    mime_type         VARCHAR(100) NULL,
    size              INT UNSIGNED NOT NULL DEFAULT 0,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_comment_attachments_comment FOREIGN KEY (comment_id) REFERENCES ticket_comments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_ticket_comment_attachments_comment ON ticket_comment_attachments(comment_id);
