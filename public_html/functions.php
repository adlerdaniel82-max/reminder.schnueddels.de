<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo;
    if ($pdo) return $pdo;
    // Support both Unix socket and TCP host
    $host = DB_HOST;
    if (str_starts_with($host, '/')) {
        $dsn = 'mysql:unix_socket=' . $host . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    } else {
        $dsn = 'mysql:host=' . $host . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    }
    $pdo = new PDO($dsn, DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES   => false]);
    return $pdo;
}

function reminderAuth(): AuthClient {
    static $client;
    if ($client) return $client;
    require_once dirname(__DIR__) . '/private/auth/config.php';
    $client = $auth;
    return $client;
}

function requireAuth(): array {
    $auth = reminderAuth();
    if (!$auth->isLoggedIn()) {
        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
            jsonOut(['error' => 'Unauthorized'], 401);
        }
        header('Location: https://schnueddels.de/auth/?action=login&redirect=' . urlencode(APP_URL . '/'));
        exit;
    }
    $user = $auth->getUser();
    // Cache user email for CLI cron use
    cacheUserEmail((int)$user['id'], $user['email'] ?? '');
    return $user;
}

function cacheUserEmail(int $userId, string $email): void {
    if (!$email) return;
    db()->prepare(
        'INSERT INTO reminder_user_prefs (user_id, email_cache)
         VALUES (?, ?)
         ON DUPLICATE KEY UPDATE email_cache = VALUES(email_cache)'
    )->execute([$userId, $email]);
}

function getUserEmailForCron(int $userId): string {
    $stmt = db()->prepare('SELECT email_override, email_cache FROM reminder_user_prefs WHERE user_id = ?');
    $stmt->execute([$userId]);
    $prefs = $stmt->fetch();
    return $prefs['email_override'] ?? $prefs['email_cache'] ?? '';
}

function jsonOut(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}
