<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$envFile = dirname(__DIR__) . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim((string)$line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$k, $v] = explode('=', $line, 2);
        $_ENV[trim($k)] = trim($v);
    }
}

defined('AUTH_API_URL')        || define('AUTH_API_URL',        $_ENV['AUTH_API_URL']        ?? 'https://schnueddels.de/auth/api');
defined('AUTH_COOKIE_DOMAIN')  || define('AUTH_COOKIE_DOMAIN',  $_ENV['AUTH_COOKIE_DOMAIN']  ?? '.schnueddels.de');
defined('AUTH_SESSION_TIMEOUT')|| define('AUTH_SESSION_TIMEOUT',(int)($_ENV['AUTH_SESSION_TIMEOUT'] ?? 86400));
defined('PROJECT_KEY')         || define('PROJECT_KEY',         $_ENV['PROJECT_KEY']         ?? 'reminder');
defined('PROJECT_API_SECRET')  || define('PROJECT_API_SECRET',  $_ENV['PROJECT_API_SECRET']  ?? '');

require_once __DIR__ . '/AuthClient.php';

if (!isset($auth) || !($auth instanceof AuthClient)) {
    $auth = new AuthClient([
        'api_url'        => AUTH_API_URL,
        'project_key'    => PROJECT_KEY,
        'api_secret'     => PROJECT_API_SECRET,
        'cookie_domain'  => AUTH_COOKIE_DOMAIN,
    ]);
    $auth->init();
}
