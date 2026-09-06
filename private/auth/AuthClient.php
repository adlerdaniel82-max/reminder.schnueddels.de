<?php
/**
 * AuthClient - Client-Bibliothek für Auth-System Integration
 */

class AuthClient {
    private string $apiUrl;
    private string $projectKey;
    private string $apiSecret;
    private string $cookieDomain;
    private ?array $user = null;
    private bool $verified = false;

    public function __construct(array $config = []) {
        $this->apiUrl = $config['api_url'] ?? 'https://schnueddels.de/auth/api';
        $this->projectKey = $config['project_key'] ?? 'main';
        $this->apiSecret = $config['api_secret'] ?? '';
        $this->cookieDomain = $config['cookie_domain'] ?? '.schnueddels.de';
    }

    public function init(): bool {
        if (isset($_COOKIE['auth_session'])) {
            return $this->verifySession($_COOKIE['auth_session']);
        }

        return false;
    }

    private function verifySession(string $sessionId): bool {
        $result = $this->apiCall('verify', [
            'session_id' => $sessionId,
            'project_key' => $this->projectKey,
        ]);

        if ($result && isset($result['valid']) && $result['valid']) {
            $this->user = $result['user'] ?? null;
            $this->verified = true;
            return true;
        }

        return false;
    }

    public function getUser(): ?array {
        return $this->user;
    }

    public function isLoggedIn(): bool {
        return $this->verified && $this->user !== null;
    }

    public function isAdmin(): bool {
        if (!$this->isLoggedIn()) {
            return false;
        }

        $role = $this->user['role'] ?? '';
        return $role === 'admin' || $role === 'master';
    }

    public function isMaster(): bool {
        if (!$this->isLoggedIn()) {
            return false;
        }

        return ($this->user['role'] ?? '') === 'master';
    }

    public function getUserId(): ?int {
        return $this->user['id'] ?? null;
    }

    public function getUsername(): ?string {
        return $this->user['username'] ?? null;
    }

    public function login(string $email, string $password): array {
        $result = $this->apiCall('login', [
            'email' => $email,
            'password' => $password,
            'project_key' => $this->projectKey,
        ]);

        if ($result && isset($result['success']) && $result['success']) {
            $this->setSessionCookie($result['session_id']);
            $this->user = $result['user'] ?? null;
            $this->verified = true;
            return ['success' => true, 'redirect' => $result['redirect'] ?? '/'];
        }

        return ['success' => false, 'error' => $result['error'] ?? 'Login failed'];
    }

    public function logout(): bool {
        if (isset($_COOKIE['auth_session'])) {
            $this->apiCall('logout', ['session_id' => $_COOKIE['auth_session']]);
        }

        $this->clearSessionCookie();
        $this->user = null;
        $this->verified = false;
        return true;
    }

    public function register(string $email, string $username, string $password): array {
        $result = $this->apiCall('register', [
            'email' => $email,
            'username' => $username,
            'password' => $password,
            'project_key' => $this->projectKey,
        ]);

        if ($result && isset($result['success']) && $result['success']) {
            if (isset($result['session_id'])) {
                $this->setSessionCookie($result['session_id']);
                $this->user = $result['user'] ?? null;
                $this->verified = true;
            }
            return ['success' => true];
        }

        return ['success' => false, 'error' => $result['error'] ?? 'Registration failed'];
    }

    public function saveProgress(array $progressData): array {
        if (!$this->isLoggedIn()) {
            return ['success' => false, 'error' => 'Not logged in'];
        }

        return $this->apiCall('progress/save', [
            'session_id' => $this->getSessionId(),
            'project_key' => $this->projectKey,
            'progress' => $progressData,
        ]);
    }

    public function loadProgress(): ?array {
        if (!$this->isLoggedIn()) {
            return null;
        }

        $result = $this->apiCall('progress/load', [
            'session_id' => $this->getSessionId(),
            'project_key' => $this->projectKey,
        ]);

        return $result['progress'] ?? null;
    }

    private function apiCall(string $endpoint, array $data = [], string $method = 'POST'): ?array {
        $url = $this->resolveEndpointUrl($endpoint);
        $method = strtoupper($method);
        $timestamp = time();
        $payload = json_encode($data);
        $signature = hash_hmac('sha256', $payload . $timestamp, $this->apiSecret);

        if ($method === 'GET' && !empty($data)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($data);
        }

        $headers = [
            'Content-Type: application/json',
            'X-Project-Key: ' . $this->projectKey,
            'X-Project-Secret: ' . $this->apiSecret,
            'X-Signature: ' . $signature,
            'X-Timestamp: ' . $timestamp,
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return $decoded;
        }

        return $decoded;
    }

    private function resolveEndpointUrl(string $endpoint): string {
        $endpoint = trim($endpoint, '/');

        if (str_starts_with($endpoint, 'progress/')) {
            $endpoint = 'progress.php';
        } elseif (!str_ends_with($endpoint, '.php')) {
            $endpoint .= '.php';
        }

        return rtrim($this->apiUrl, '/') . '/' . $endpoint;
    }

    private function setSessionCookie(string $sessionId): void {
        $this->applySessionCookie($sessionId, time() + 86400);
        $this->applyCacheBypassCookie('1', time() + 86400);
    }

    private function clearSessionCookie(): void {
        $this->applySessionCookie('', time() - 3600);
        $this->applyCacheBypassCookie('', time() - 3600);
    }

    private function getSessionId(): ?string {
        return $_COOKIE['auth_session'] ?? null;
    }

    private function applySessionCookie(string $value, int $expires): void {
        setcookie('auth_session', $value, [
            'expires' => $expires,
            'path' => '/',
            'domain' => $this->cookieDomain,
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private function applyCacheBypassCookie(string $value, int $expires): void {
        setcookie('AUTHSESS', $value, [
            'expires' => $expires,
            'path' => '/',
            'domain' => $this->cookieDomain,
            'secure' => true,
            'httponly' => false,
            'samesite' => 'Lax',
        ]);
    }

    public function getLoginUrl(?string $redirect = null): string {
        $url = 'https://schnueddels.de/auth/';
        if ($redirect) {
            $url .= '?redirect=' . urlencode($redirect);
        }
        return $url;
    }

    public function getRegisterUrl(): string {
        return 'https://schnueddels.de/auth/?action=register';
    }

    public function getDashboardUrl(): string {
        return 'https://schnueddels.de/auth/account.php';
    }
}
