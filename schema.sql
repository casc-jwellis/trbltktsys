CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name     VARCHAR(100) NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
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
    assigned_to      INT UNSIGNED NULL,
    internal_notes   TEXT NULL,
    attachment_path  VARCHAR(255) NULL,
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_tickets_assigned_to FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_tickets_status ON tickets(status);
CREATE INDEX idx_tickets_category ON tickets(category);
