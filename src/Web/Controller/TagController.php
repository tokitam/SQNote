<?php

declare(strict_types=1);

namespace SQNote\Web\Controller;

use SQNote\Repository\NoteRepository;
use SQNote\Repository\NotebookRepository;
use SQNote\Repository\TagRepository;

class TagController extends AbstractController
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
        $this->render('tag/index.html.twig', [
            'tags'      => $this->tags->findAll(),
            'notebooks' => $this->notebooks->findAll(),
        ]);
    }

    public function show(array $params): never
    {
        $tagName = urldecode($params['name']);
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $limit   = 20;
        $offset  = ($page - 1) * $limit;

        $opts  = ['tag' => $tagName, 'limit' => $limit, 'offset' => $offset];
        $list  = $this->notes->findAll($opts);
        $total = $this->notes->count($opts);

        $this->render('note/index.html.twig', [
            'notes'       => $list,
            'total'       => $total,
            'page'        => $page,
            'pages'       => (int) ceil($total / $limit),
            'active_tag'  => $tagName,
            'notebooks'   => $this->notebooks->findAll(),
            'tags'        => $this->tags->findAll(),
        ]);
    }
}
