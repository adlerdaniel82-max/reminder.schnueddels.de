<?php
declare(strict_types=1);

$envFile = dirname(__DIR__) . '/private/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

define('DB_HOST',    $_ENV['DB_HOST']    ?? '/var/run/mysqld/mysqld.sock');
define('DB_NAME',    $_ENV['DB_NAME']    ?? 'webuser_reminder');
define('DB_USER',    $_ENV['DB_USER']    ?? 'webuser_reminder');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_CHARSET', 'utf8mb4');

define('SMTP_HOST', $_ENV['SMTP_HOST'] ?? 'localhost');
define('SMTP_PORT', (int)($_ENV['SMTP_PORT'] ?? 465));
define('SMTP_USER', $_ENV['SMTP_USER'] ?? 'notification@reminder.schnueddels.de');
define('SMTP_PASS', $_ENV['SMTP_PASS'] ?? '');
define('SMTP_FROM', 'notification@reminder.schnueddels.de');
define('SMTP_FROM_NAME', 'Reminder – Schnüddels');

define('VAPID_PUBLIC_KEY',  $_ENV['VAPID_PUBLIC_KEY']  ?? '');
define('VAPID_PRIVATE_KEY', $_ENV['VAPID_PRIVATE_KEY'] ?? '');
define('VAPID_SUBJECT',     'mailto:' . SMTP_FROM);

define('APP_URL', 'https://reminder.schnueddels.de');
