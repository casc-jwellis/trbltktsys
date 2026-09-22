-- Inbound mail settings (Admin Settings -> Email), used by bin/imap-poll.php
-- to pull submitter replies in over IMAP via the vendored lib/tehimap client.
-- Single settings row, always id 1, mirroring smtp_settings.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

CREATE TABLE imap_settings (
    id                 TINYINT UNSIGNED PRIMARY KEY,
    enabled            TINYINT(1) NOT NULL DEFAULT 0,
    host               VARCHAR(150) NOT NULL DEFAULT '',
    port               SMALLINT UNSIGNED NOT NULL DEFAULT 993,
    encryption         VARCHAR(10) NOT NULL DEFAULT 'ssl',
    username           VARCHAR(150) NULL,
    password           VARCHAR(255) NULL,
    mailbox            VARCHAR(150) NOT NULL DEFAULT 'INBOX',
    processed_mailbox  VARCHAR(150) NOT NULL DEFAULT 'Processed',
    unmatched_mailbox  VARCHAR(150) NOT NULL DEFAULT 'Unmatched',
    updated_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO imap_settings (id) VALUES (1);
