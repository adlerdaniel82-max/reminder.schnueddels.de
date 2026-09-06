-- 2026-04-09: Kalender-Modi und Reminder-Farben
ALTER TABLE reminders
  ADD COLUMN color VARCHAR(7) NULL DEFAULT NULL
  AFTER description;

ALTER TABLE reminder_user_prefs
  ADD COLUMN cal_view ENUM('compact','full','list') NOT NULL DEFAULT 'compact'
  AFTER theme;
