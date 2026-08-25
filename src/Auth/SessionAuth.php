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

        // 専用のセッション保存先を使い、他 vhost / Debian の sessionclean cron に
        // 消されないようにする。保存先が共有の /var/lib/php/sessions のままだと、
        // その GC 設定（既定 24 分）でファイルが削除されセッションが早期に切れる。
        $savePath = $sessionConfig['save_path'] ?? __DIR__ . '/../../data/sessions';
        if (!is_dir($savePath)) {
            @mkdir($savePath, 0700, true);
        }
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        // サーバ側のセッションデータ寿命を Cookie の寿命に合わせる（既定は 1440 秒）。
        // これを延ばさないと、Cookie が生きていてもサーバ側で GC され切れてしまう。
        ini_set('session.gc_maxlifetime', (string)$lifetime);
        // 専用保存先なので、自前 GC を有効にして古いファイルを掃除する。
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        session_name($name);
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // スライディング有効期限: アクセスのたびに Cookie を再送し、
        // 認証済みなら失効までの時間を毎回リセットする。
        if (($_SESSION['authenticated'] ?? false) && !headers_sent()) {
            setcookie($name, session_id(), [
                'expires'  => time() + $lifetime,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
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
