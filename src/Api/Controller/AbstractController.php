<?php

declare(strict_types=1);

namespace SQNote\Api\Controller;

abstract class AbstractController
{
    protected function json(mixed $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'data' => $data], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function jsonList(array $data, int $total, int $limit, int $offset): never
    {
        http_response_code(200);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok'   => true,
            'data' => $data,
            'meta' => compact('total', 'limit', 'offset'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    protected function error(string $code, string $message, int $status = 400): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['ok' => false, 'error' => ['code' => $code, 'message' => $message]],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    protected function body(): array
    {
        $raw = file_get_contents('php://input');
        return $raw ? (json_decode($raw, true) ?? []) : [];
    }

    protected function notFound(string $resource = 'Resource'): never
    {
        $this->error('NOT_FOUND', "{$resource} not found", 404);
    }
}
