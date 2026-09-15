-- Adds SMTP settings (Admin Settings -> Email) used to send mail via PHPMailer.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

CREATE TABLE smtp_settings (
    id          TINYINT UNSIGNED PRIMARY KEY,
    host        VARCHAR(150) NOT NULL DEFAULT '',
    port        SMALLINT UNSIGNED NOT NULL DEFAULT 587,
    encryption  VARCHAR(10) NOT NULL DEFAULT 'tls',
    username    VARCHAR(150) NULL,
    password    VARCHAR(255) NULL,
    from_email  VARCHAR(150) NULL,
    from_name   VARCHAR(100) NULL,
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Single settings row, always id 1 -- updated in place, never inserted again.
INSERT INTO smtp_settings (id) VALUES (1);
