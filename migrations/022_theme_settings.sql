-- Site name and theme color (Admin Settings -> Theme). Single settings row,
-- always id 1, mirroring smtp_settings. A NULL site_name means "use the name
-- from config/config.php"; color is a key of THEME_PRESETS in includes/theme.php.
-- Safe to run against a database created before this change.
-- (Skip this file entirely on a brand-new database -- schema.sql already includes it.)

CREATE TABLE theme_settings (
    id          TINYINT UNSIGNED PRIMARY KEY,
    site_name   VARCHAR(100) NULL,
    color       VARCHAR(20) NOT NULL DEFAULT 'institution',
    updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO theme_settings (id) VALUES (1);
