-- "Sign in with Google" settings (Admin Settings -> Sign-in). Single settings
-- row, always id 1, mirroring smtp_settings. The client ID is not a secret.
-- allowed_domain is the only email domain (and Google Workspace domain) whose
-- accounts may sign in this way.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

CREATE TABLE google_signin_settings (
    id              TINYINT UNSIGNED PRIMARY KEY,
    enabled         TINYINT(1) NOT NULL DEFAULT 0,
    client_id       VARCHAR(150) NOT NULL DEFAULT '',
    allowed_domain  VARCHAR(100) NOT NULL DEFAULT 'carlalbert.edu',
    updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO google_signin_settings (id) VALUES (1);
