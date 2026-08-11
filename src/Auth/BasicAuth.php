<?php

declare(strict_types=1);

namespace SQNote\Auth;

class BasicAuth
{
    public function __construct(
        private string $expectedUser,
        private string $expectedPass,
    ) {}

    public function check(): bool
    {
        // パスワード未設定は常に失敗（素通り禁止）
        if ($this->expectedPass === '') {
            return false;
        }

        [$user, $pass] = $this->resolveCredentials();

        return hash_equals($this->expectedUser, $user)
            && hash_equals($this->expectedPass, $pass);
    }

    public function challenge(bool $isApi): never
    {
        header('WWW-Authenticate: Basic realm="SQNote"');
        http_response_code(401);

        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'ok'    => false,
                'error' => ['code' => 'UNAUTHORIZED', 'message' => 'Unauthorized'],
            ]);
        } else {
            echo '<!DOCTYPE html><html><body><h1>401 Unauthorized</h1><p>認証が必要です。</p></body></html>';
        }

        exit;
    }

    private function resolveCredentials(): array
    {
        // 通常の PHP_AUTH_USER / PHP_AUTH_PW
        if (isset($_SERVER['PHP_AUTH_USER'])) {
            return [$_SERVER['PHP_AUTH_USER'], $_SERVER['PHP_AUTH_PW'] ?? ''];
        }

        // PHP-FPM 環境で Authorization ヘッダーから取り出す
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if (str_starts_with($header, 'Basic ')) {
            $decoded = base64_decode(substr($header, 6), true);
            if ($decoded !== false && str_contains($decoded, ':')) {
                [$user, $pass] = explode(':', $decoded, 2);
                return [$user, $pass];
            }
        }

        return ['', ''];
    }
}
