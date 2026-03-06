CREATE DATABASE IF NOT EXISTS carequeue CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE carequeue;

CREATE TABLE IF NOT EXISTS departments (
    id    INT AUTO_INCREMENT PRIMARY KEY,
    code  VARCHAR(20)  NOT NULL UNIQUE,
    label VARCHAR(100) NOT NULL
);
INSERT INTO departments (code, label) VALUES
  ('ER',     'Emergency Room'),
  ('OPD',    'Out-Patient'),
  ('Pedia',  'Pediatrics'),
  ('Cardio', 'Cardiology'),
  ('Ortho',  'Orthopedics'),
  ('Neuro',  'Neurology');

CREATE TABLE IF NOT EXISTS statuses (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
);

INSERT INTO statuses (name) VALUES
  ('Waiting'), ('In Progress'), ('Served'), ('Cancelled');

CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    fname      VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Default admin account (password: password)
INSERT INTO admins (fname, email, password) VALUES
('Admin', 'admin@carequeue.com',
 '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

CREATE TABLE IF NOT EXISTS staff (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    fname      VARCHAR(100) NOT NULL,
    email      VARCHAR(150) NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS patients (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    queue_no      VARCHAR(20)  NOT NULL,
    name          VARCHAR(100) NOT NULL,
    mobile        VARCHAR(15)  NOT NULL,
    dept_id       INT          NOT NULL,
    status_id     INT          NOT NULL DEFAULT 1,
    notes         TEXT,
    registered_by INT,
    registered_by_role VARCHAR(10) NOT NULL DEFAULT 'staff',
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (dept_id)   REFERENCES departments(id) ON UPDATE CASCADE,
    FOREIGN KEY (status_id) REFERENCES statuses(id)    ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS queue_counters (
    dept_id    INT         NOT NULL PRIMARY KEY,
    counter    INT         NOT NULL DEFAULT 0,
    last_reset DATE        NOT NULL DEFAULT (CURDATE()),
    FOREIGN KEY (dept_id) REFERENCES departments(id) ON UPDATE CASCADE
);

INSERT IGNORE INTO queue_counters (dept_id, counter, last_reset)
SELECT id, 0, CURDATE() FROM departments;
