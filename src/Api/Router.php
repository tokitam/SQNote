<?php

declare(strict_types=1);

namespace SQNote\Api;

use SQNote\Api\Controller\AttachmentController;
use SQNote\Api\Controller\ExportController;
use SQNote\Api\Controller\HealthController;
use SQNote\Api\Controller\NoteController;
use SQNote\Api\Controller\NoteHistoryController;
use SQNote\Api\Controller\NotebookController;
use SQNote\Api\Controller\TagController;
use SQNote\Container;

class Router
{
    private array $routes = [];

    public function __construct(private Container $container)
    {
        $this->registerRoutes();
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }
            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler($params);
                return;
            }
        }

        http_response_code(404);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Endpoint not found']]);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [$method, $pattern, $handler];
    }

    private function registerRoutes(): void
    {
        $c = $this->container;

        // ヘルスチェック
        $this->add('GET', '#^/health$#', fn($p) =>
            (new HealthController($c->config(), $c->pdo()))->index()
        );

        // ノートブック
        $nb = fn() => new NotebookController($c->notebookRepository(), $c->notebookService());
        $this->add('GET',    '#^/notebooks$#',                      fn($p) => $nb()->index());
        $this->add('POST',   '#^/notebooks$#',                      fn($p) => $nb()->create());
        $this->add('PUT',    '#^/notebooks/(?P<id>[^/]+)$#',        fn($p) => $nb()->update($p));
        $this->add('DELETE', '#^/notebooks/(?P<id>[^/]+)$#',        fn($p) => $nb()->delete($p));

        // ノート
        $note = fn() => new NoteController($c->noteRepository(), $c->noteService());
        $this->add('GET',    '#^/notes$#',                          fn($p) => $note()->index());
        $this->add('POST',   '#^/notes$#',                          fn($p) => $note()->create());
        $this->add('GET',    '#^/notes/(?P<id>[^/]+)$#',            fn($p) => $note()->show($p));
        $this->add('PUT',    '#^/notes/(?P<id>[^/]+)$#',            fn($p) => $note()->update($p));
        $this->add('DELETE', '#^/notes/(?P<id>[^/]+)$#',            fn($p) => $note()->delete($p));
        $this->add('POST',   '#^/notes/(?P<note_id>[^/]+)/attachments$#',
            fn($p) => $note()->createAttachment($p)
        );

        // ノート履歴
        $hist = fn() => new NoteHistoryController(
            $c->noteRepository(), $c->noteHistoryRepository(), $c->noteService()
        );
        $this->add('GET',  '#^/notes/(?P<id>[^/]+)/history$#',                          fn($p) => $hist()->index($p));
        $this->add('GET',  '#^/notes/(?P<id>[^/]+)/history/(?P<hid>[^/]+)$#',           fn($p) => $hist()->show($p));
        $this->add('POST', '#^/notes/(?P<id>[^/]+)/history/(?P<hid>[^/]+)/restore$#',   fn($p) => $hist()->restore($p));

        // タグ
        $tag = fn() => new TagController($c->tagRepository());
        $this->add('GET',    '#^/tags$#',                           fn($p) => $tag()->index());
        $this->add('DELETE', '#^/tags/(?P<id>[^/]+)$#',             fn($p) => $tag()->delete($p));

        // 添付ファイル
        $att = fn() => new AttachmentController($c->attachmentRepository(), $c->noteService());
        $this->add('GET',    '#^/attachments/(?P<id>[^/]+)$#',      fn($p) => $att()->show($p));
        $this->add('DELETE', '#^/attachments/(?P<id>[^/]+)$#',      fn($p) => $att()->delete($p));

        // エクスポート
        $exp = fn() => new ExportController($c->exportService());
        $this->add('GET', '#^/export/json$#',                       fn($p) => $exp()->json());
        $this->add('GET', '#^/export/markdown$#',                   fn($p) => $exp()->markdown());
    }
}
