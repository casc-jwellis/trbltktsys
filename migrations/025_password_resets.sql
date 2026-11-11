-- The one-time links behind "Forgot password" on the login page. Only a hash
-- of each link's secret is kept (so a copy of this table can't be used to reset
-- anyone's password), a link is good for one hour and one use, and asking again
-- replaces the earlier link. Times are stored in UTC.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

CREATE TABLE password_resets (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id       INT UNSIGNED NOT NULL,
    token_hash    CHAR(64) NOT NULL UNIQUE,
    created_at    DATETIME NOT NULL,
    expires_at    DATETIME NOT NULL,
    requested_ip  VARCHAR(45) NOT NULL DEFAULT '',
    KEY idx_password_resets_user (user_id),
    KEY idx_password_resets_ip (requested_ip, created_at),
    CONSTRAINT fk_password_resets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
