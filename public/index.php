<?php

declare(strict_types=1);

// PHP 組み込みサーバー用: 実在する静的ファイルはそのまま返す
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    if (is_file($file)) {
        return false;
    }
}

require __DIR__ . '/../vendor/autoload.php';

\Dotenv\Dotenv::createUnsafeImmutable(__DIR__ . '/..')->safeLoad();

$config    = require __DIR__ . '/../config/config.php';
$container = new \SQNote\Container($config);

$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// BASIC 認証
$auth = new \SQNote\Auth\BasicAuth($config['auth']['user'], $config['auth']['pass']);
if (!$auth->check()) {
    $auth->challenge(str_starts_with($uri, '/api/'));
}

try {
    if (str_starts_with($uri, '/api/v1')) {
        $apiPath = preg_replace('#^/api/v1#', '', $uri) ?: '/';
        $router  = new \SQNote\Api\Router($container);
        $router->dispatch($method, $apiPath);
    } else {
        $router = new \SQNote\Web\Router($container);
        $router->dispatch($method, $uri);
    }
} catch (\Throwable $e) {
    if (!\SQNote\Support\DiskFull::isCausedBy($e)) {
        throw $e;
    }

    $freeBytes = \SQNote\Support\DiskFull::freeBytes($config['db']['path']);
    http_response_code(507);

    if (str_starts_with($uri, '/api/')) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'    => false,
            'error' => [
                'code'       => 'DISK_FULL',
                'message'    => 'サーバーのディスク容量が不足しているため、保存できませんでした。',
                'free_bytes' => $freeBytes,
            ],
        ]);
    } else {
        echo \SQNote\Support\DiskFull::renderHtml($freeBytes);
    }
}
