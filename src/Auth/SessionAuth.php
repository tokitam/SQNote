<?php

declare(strict_types=1);

namespace SQNote\Auth;

class SessionAuth
{
    public function start(array $sessionConfig): void
    {
        $lifetime = (int)($sessionConfig['lifetime'] ?? 604800);
        $name     = $sessionConfig['name'] ?? 'sqnote_sess';
        $secure   = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }

    public function login(string $user, string $pass, string $expectedUser, string $expectedPass): bool
    {
        if ($expectedPass === '') {
            return false;
        }

        if (!hash_equals($expectedUser, $user) || !hash_equals($expectedPass, $pass)) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['user']          = $user;

        return true;
    }

    public function isAuthenticated(): bool
    {
        return $_SESSION['authenticated'] ?? false;
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        session_destroy();
    }

    public function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(16));
        }

        return $_SESSION['csrf_token'];
    }

    public function verifyCsrfToken(string $token): bool
    {
        $expected = $_SESSION['csrf_token'] ?? '';

        return $expected !== '' && hash_equals($expected, $token);
    }
}
