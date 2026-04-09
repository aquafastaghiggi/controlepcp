<?php

declare(strict_types=1);

namespace App\Auth;

final class Auth
{
    private const SESSION_KEY_USER = 'auth_user';
    private const SESSION_KEY_CSRF = 'csrf_token';
    private const ADMIN_USERNAME = 'aghiggi';

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_start();
    }

    public static function appBasePath(): string
    {
        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        $dir = str_replace('\\', '/', dirname($scriptName));
        $dir = rtrim($dir, '/');

        if (preg_match('#/api$#', $dir)) {
            $dir = substr($dir, 0, -4);
        }

        return $dir;
    }

    public static function loginUrl(): string
    {
        $base = self::appBasePath();
        return ($base !== '' ? $base : '') . '/login.php';
    }

    public static function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }

    public static function requireLogin(): void
    {
        if (self::user() !== null) {
            return;
        }

        self::redirect(self::loginUrl());
    }

    public static function isAdmin(): bool
    {
        $user = self::user();
        if ($user === null) {
            return false;
        }

        $username = strtolower(trim((string) ($user['username'] ?? '')));
        return $username !== '' && $username === strtolower(self::ADMIN_USERNAME);
    }

    public static function requireAdmin(): void
    {
        self::requireLogin();

        if (self::isAdmin()) {
            return;
        }

        http_response_code(403);
        echo 'Acesso negado.';
        exit;
    }

    public static function requireLoginApi(): void
    {
        if (self::user() !== null) {
            return;
        }

        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => 'N?o autenticado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function user(): ?array
    {
        $user = $_SESSION[self::SESSION_KEY_USER] ?? null;
        return is_array($user) ? $user : null;
    }

    public static function setUser(array $user): void
    {
        $_SESSION[self::SESSION_KEY_USER] = [
            'id' => (int) ($user['id'] ?? 0),
            'name' => (string) ($user['name'] ?? ''),
            'username' => (string) ($user['username'] ?? ''),
        ];
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY_USER]);
    }

    public static function csrfToken(): string
    {
        $token = $_SESSION[self::SESSION_KEY_CSRF] ?? null;
        if (is_string($token) && $token !== '') {
            return $token;
        }

        $token = bin2hex(random_bytes(16));
        $_SESSION[self::SESSION_KEY_CSRF] = $token;

        return $token;
    }

    public static function requireCsrf(?string $token): void
    {
        $expected = (string) ($_SESSION[self::SESSION_KEY_CSRF] ?? '');
        if ($expected !== '' && is_string($token) && hash_equals($expected, $token)) {
            return;
        }

        http_response_code(400);
        echo 'Falha de valida??o (CSRF).';
        exit;
    }

    public static function regenerateSessionId(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
