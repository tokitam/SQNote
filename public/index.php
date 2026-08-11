<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$config = require __DIR__ . '/../config/config.php';

// ルーティング実装は #6（REST API）・#7（BASIC 認証）・#8（Web UI）で追加する
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if (str_starts_with($uri, '/api/v1')) {
    // API ルーター（#6 で実装）
    http_response_code(501);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => ['code' => 'NOT_IMPLEMENTED', 'message' => 'API not yet implemented']]);
} else {
    // Web ルーター（#8 で実装）
    http_response_code(501);
    echo '<h1>SQNote</h1><p>Web UI not yet implemented.</p>';
}
