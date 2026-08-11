<?php

declare(strict_types=1);

namespace SQNote\Web\Controller;

use SQNote\Repository\NoteRepository;
use SQNote\Repository\NotebookRepository;
use SQNote\Repository\TagRepository;

class NotebookController extends AbstractController
{
    public function __construct(
        \Twig\Environment $twig,
        private NoteRepository $notes,
        private NotebookRepository $notebooks,
        private TagRepository $tags,
    ) {
        parent::__construct($twig);
    }

    public function index(): never
    {
        $this->render('notebook/index.html.twig', [
            'notebooks' => $this->notebooks->findAll(),
            'tags'      => $this->tags->findAll(),
        ]);
    }

    public function show(array $params): never
    {
        $notebook = $this->notebooks->findById($params['id']);
        if (!$notebook) {
            http_response_code(404);
            echo '<h1>404 Not Found</h1>';
            exit;
        }

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $opts  = ['notebook_id' => $params['id'], 'limit' => $limit, 'offset' => $offset];
        $list  = $this->notes->findAll($opts);
        $total = $this->notes->count($opts);

        $this->render('notebook/show.html.twig', [
            'notebook'  => $notebook,
            'notes'     => $list,
            'total'     => $total,
            'page'      => $page,
            'pages'     => (int) ceil($total / $limit),
            'notebooks' => $this->notebooks->findAll(),
            'tags'      => $this->tags->findAll(),
        ]);
    }
}
