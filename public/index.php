<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$config    = require __DIR__ . '/../config/config.php';
$container = new \SQNote\Container($config);

$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// BASIC 認証
$auth = new \SQNote\Auth\BasicAuth($config['auth']['user'], $config['auth']['pass']);
if (!$auth->check()) {
    $auth->challenge(str_starts_with($uri, '/api/'));
}

if (str_starts_with($uri, '/api/v1')) {
    $apiPath = preg_replace('#^/api/v1#', '', $uri) ?: '/';
    $router  = new \SQNote\Api\Router($container);
    $router->dispatch($method, $apiPath);
} else {
    // Web ルーター（#8 で実装）
    http_response_code(501);
    echo '<h1>SQNote</h1><p>Web UI not yet implemented.</p>';
}
