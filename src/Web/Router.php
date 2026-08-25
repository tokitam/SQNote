<?php

declare(strict_types=1);

namespace SQNote\Web;

use SQNote\Auth\SessionAuth;
use SQNote\Container;
use SQNote\Web\Controller\AuthController;
use SQNote\Web\Controller\ExportController;
use SQNote\Web\Controller\ImportController;
use SQNote\Web\Controller\NotebookController;
use SQNote\Web\Controller\NoteController;
use SQNote\Web\Controller\NoteEditController;
use SQNote\Web\Controller\NoteHistoryController;
use SQNote\Web\Controller\TagController;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class Router
{
    private array $routes = [];
    private Environment $twig;

    public function __construct(private Container $container, private ?SessionAuth $sessionAuth = null)
    {
        $loader     = new FilesystemLoader(dirname(__DIR__, 2) . '/templates');
        $this->twig = new Environment($loader, ['cache' => false]);
        $this->registerRoutes();
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== $method && $routeMethod !== 'ANY') {
                continue;
            }
            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler($params);
                return;
            }
        }

        http_response_code(404);
        echo '<h1>404 Not Found</h1>';
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [$method, $pattern, $handler];
    }

    private function registerRoutes(): void
    {
        $c    = $this->container;
        $twig = $this->twig;

        // 認証ルート
        $authCfg = $c->config()['auth'];
        $session = $this->sessionAuth ?? new SessionAuth();
        $auth = fn() => new AuthController($twig, $session, $authCfg);
        $this->add('GET',  '#^/login$#',  fn($p) => $auth()->loginForm());
        $this->add('POST', '#^/login$#',  fn($p) => $auth()->loginPost());
        $this->add('GET',  '#^/logout$#', fn($p) => $auth()->logout());

        $note = fn() => new NoteController(
            $twig, $c->noteRepository(), $c->notebookRepository(), $c->tagRepository()
        );
        $edit = fn() => new NoteEditController(
            $twig, $c->noteRepository(), $c->notebookRepository(), $c->tagRepository(), $c->noteService()
        );
        $nb = fn() => new NotebookController(
            $twig, $c->noteRepository(), $c->notebookRepository(), $c->tagRepository()
        );
        $tag = fn() => new TagController(
            $twig, $c->noteRepository(), $c->notebookRepository(), $c->tagRepository()
        );

        $noteHist = fn() => new NoteHistoryController(
            $twig, $c->noteRepository(), $c->noteHistoryRepository(),
            $c->notebookRepository(), $c->tagRepository()
        );

        // 閲覧・作成・編集（固定パスを動的パスより先に登録する）
        $this->add('GET',  '#^/$#',                                   fn($p) => $note()->index());
        $this->add('GET',  '#^/search$#',                             fn($p) => $note()->search());
        $this->add('GET',  '#^/notes/new$#',                          fn($p) => $edit()->create());
        $this->add('GET',  '#^/notes/(?P<id>[^/]+)/edit$#',           fn($p) => $edit()->edit($p));
        $this->add('GET',  '#^/notes/(?P<id>[^/]+)/history/(?P<hid>[^/]+)/diff$#', fn($p) => $noteHist()->diff($p));
        $this->add('GET',  '#^/notes/(?P<id>[^/]+)/history$#',                    fn($p) => $noteHist()->index($p));
        $this->add('GET',  '#^/notes/(?P<id>[^/]+)$#',                            fn($p) => $note()->show($p));
        $this->add('POST', '#^/notes$#',                         fn($p) => $edit()->store());
        $this->add('POST', '#^/notes/(?P<id>[^/]+)$#',           fn($p) => $edit()->update($p));
        $this->add('GET',  '#^/notebooks$#',                     fn($p) => $nb()->index());
        $this->add('GET',  '#^/notebooks/(?P<id>[^/]+)$#',       fn($p) => $nb()->show($p));
        $this->add('GET',  '#^/tags$#',                          fn($p) => $tag()->index());
        $this->add('GET',  '#^/tags/(?P<name>[^/]+)$#',          fn($p) => $tag()->show($p));

        // インポート
        $import = fn() => new ImportController(
            $twig, $c->notebookRepository(), $c->tagRepository(), $c->notebookService(), $c->enexImporter()
        );
        $this->add('GET',  '#^/import$#', fn($p) => $import()->index());
        $this->add('POST', '#^/import$#', fn($p) => $import()->store());

        // エクスポート
        $export = fn() => new ExportController(
            $twig, $c->notebookRepository(), $c->tagRepository(), $c->exportService(), $c->config()
        );
        $this->add('GET', '#^/export$#',          fn($p) => $export()->index());
        $this->add('GET', '#^/export/json$#',     fn($p) => $export()->downloadJson());
        $this->add('GET', '#^/export/markdown$#', fn($p) => $export()->downloadMarkdown());
        $this->add('GET', '#^/export/backup$#',   fn($p) => $export()->downloadBackup());
    }
}
