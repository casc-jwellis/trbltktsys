-- Turns ticket categories into a manageable table (Admin Settings -> Categories),
-- assignable to users and groups.
-- Safe to run against a database created before this feature existed.
-- (Skip this file entirely on a brand-new database — schema.sql already includes it.)

CREATE TABLE IF NOT EXISTS categories (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed the categories that used to be hardcoded, then pick up anything else
-- already in use on existing tickets.
INSERT IGNORE INTO categories (name)
VALUES ('General'), ('Hardware'), ('Software'), ('Network'), ('Account Access'), ('Other');

INSERT IGNORE INTO categories (name)
SELECT DISTINCT category FROM tickets;

CREATE TABLE IF NOT EXISTS user_categories (
    user_id     INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, category_id),
    CONSTRAINT fk_user_categories_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS group_categories (
    group_id    INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, category_id),
    CONSTRAINT fk_group_categories_group FOREIGN KEY (group_id) REFERENCES agent_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_category FOREIGN KEY (category) REFERENCES categories(name) ON UPDATE CASCADE;
