CREATE TABLE IF NOT EXISTS reminders (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    user_id         INT NOT NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT NULL,
    remind_at       DATETIME NOT NULL,
    recurrence      ENUM('none','daily','weekly','monthly','yearly') NOT NULL DEFAULT 'none',
    recurrence_end  DATE NULL,
    email_notify    TINYINT(1) NOT NULL DEFAULT 0,
    email_lead_time INT NULL,
    status          ENUM('active','done','snoozed') NOT NULL DEFAULT 'active',
    snoozed_until   DATETIME NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_remind (user_id, remind_at),
    INDEX idx_user_status (user_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS reminder_user_prefs (
    user_id             INT NOT NULL PRIMARY KEY,
    default_email_lead  INT NOT NULL DEFAULT 1440,
    email_override      VARCHAR(255) NULL,
    email_cache         VARCHAR(255) NULL,
    theme               VARCHAR(16) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS push_subscriptions (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    endpoint    TEXT NOT NULL,
    p256dh      TEXT NOT NULL,
    auth        TEXT NOT NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_log (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    reminder_id     INT NOT NULL,
    scheduled_for   DATETIME NOT NULL,
    sent_at         TIMESTAMP NULL,
    status          ENUM('sent','failed') NOT NULL,
    UNIQUE KEY uniq_reminder_scheduled (reminder_id, scheduled_for),
    INDEX idx_reminder_id (reminder_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS import_log (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    user_id     INT NOT NULL,
    filename    VARCHAR(255) NOT NULL,
    format      ENUM('ics','csv') NOT NULL,
    imported    INT NOT NULL DEFAULT 0,
    skipped     INT NOT NULL DEFAULT 0,
    imported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
