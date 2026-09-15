CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NULL,
    phone         VARCHAR(30) NULL,
    is_admin      TINYINT(1) NOT NULL DEFAULT 0,
    disabled      TINYINT(1) NOT NULL DEFAULT 0,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE agent_groups (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(60) NOT NULL UNIQUE,
    description VARCHAR(255) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE user_agent_groups (
    user_id  INT UNSIGNED NOT NULL,
    group_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, group_id),
    CONSTRAINT fk_user_agent_groups_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_agent_groups_group FOREIGN KEY (group_id) REFERENCES agent_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE categories (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name) VALUES ('General'), ('Hardware'), ('Software'), ('Network'), ('Account Access'), ('Other');

CREATE TABLE group_categories (
    group_id    INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (group_id, category_id),
    CONSTRAINT fk_group_categories_group FOREIGN KEY (group_id) REFERENCES agent_groups(id) ON DELETE CASCADE,
    CONSTRAINT fk_group_categories_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE requesters (
    email      VARCHAR(150) NOT NULL PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    phone      VARCHAR(30) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE tickets (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    requester_name   VARCHAR(100) NOT NULL,
    requester_email  VARCHAR(150) NOT NULL,
    subject          VARCHAR(200) NOT NULL,
    description      TEXT NOT NULL,
    category         VARCHAR(30) NOT NULL DEFAULT 'General',
    priority         VARCHAR(10) NOT NULL DEFAULT 'Medium',
    status           VARCHAR(20) NOT NULL DEFAULT 'Open',
    attachment_path  VARCHAR(255) NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_requester FOREIGN KEY (requester_email) REFERENCES requesters(email) ON UPDATE CASCADE,
    CONSTRAINT fk_tickets_category FOREIGN KEY (category) REFERENCES categories(name) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_category ON tickets(category);

-- Who a ticket is currently assigned to. Seeded from group_categories for
-- the ticket's category when it's submitted, and freely editable afterward
-- by helpdesk agents (a ticket can be assigned to any number of groups).
CREATE TABLE ticket_assigned_groups (
    ticket_id INT UNSIGNED NOT NULL,
    group_id  INT UNSIGNED NOT NULL,
    PRIMARY KEY (ticket_id, group_id),
    CONSTRAINT fk_ticket_assigned_groups_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_assigned_groups_group FOREIGN KEY (group_id) REFERENCES agent_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- The ticket conversation: agent responses meant for the submitter, and
-- internal notes meant only for other staff. Replaces the old single
-- tickets.internal_notes field with a proper chronological thread.
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

-- Pre-written text agents can drop into a ticket response (Admin Settings -> Responses).
CREATE TABLE canned_responses (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(100) NOT NULL,
    body       TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SMTP settings (Admin Settings -> Email) used to send mail via PHPMailer.
-- Single settings row, always id 1.
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

INSERT INTO smtp_settings (id) VALUES (1);
