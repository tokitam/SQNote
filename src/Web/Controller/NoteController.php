<?php

declare(strict_types=1);

namespace SQNote\Web\Controller;

use League\CommonMark\CommonMarkConverter;
use SQNote\Repository\NoteRepository;
use SQNote\Repository\NotebookRepository;
use SQNote\Repository\TagRepository;

class NoteController extends AbstractController
{
    private const ALLOWED_TAGS = '<div><p><h1><h2><h3><h4><h5><h6><ul><ol><li><blockquote><pre><hr><br><table><thead><tbody><tr><th><td><span><a><strong><em><u><s><code><img><input>';

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
        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $opts  = ['limit' => $limit, 'offset' => $offset];
        $list  = $this->notes->findAll($opts);
        $total = $this->notes->count($opts);

        $this->render('note/index.html.twig', [
            'notes'     => $list,
            'total'     => $total,
            'page'      => $page,
            'pages'     => (int) ceil($total / $limit),
            'notebooks' => $this->notebooks->findAll(),
            'tags'      => $this->tags->findAll(),
        ]);
    }

    public function show(array $params): never
    {
        $note = $this->notes->findById($params['id']);
        if (!$note) {
            http_response_code(404);
            echo '<h1>404 Not Found</h1>';
            exit;
        }

        if ($note['content_type'] === 'markdown') {
            $converter          = new CommonMarkConverter(['html_input' => 'strip']);
            $note['content_html'] = $converter->convert($note['content'])->getContent();
        } else {
            $note['content_html'] = strip_tags($note['content'], self::ALLOWED_TAGS);
        }

        $this->render('note/show.html.twig', [
            'note'      => $note,
            'notebooks' => $this->notebooks->findAll(),
            'tags'      => $this->tags->findAll(),
        ]);
    }

    public function search(): never
    {
        $q = trim($_GET['q'] ?? '');
        if ($q === '') {
            $this->redirect('/');
        }

        $page   = max(1, (int) ($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;

        $opts  = ['q' => $q, 'limit' => $limit, 'offset' => $offset];
        $list  = $this->notes->findAll($opts);
        $total = $this->notes->count($opts);

        $this->render('note/index.html.twig', [
            'notes'     => $list,
            'total'     => $total,
            'page'      => $page,
            'pages'     => (int) ceil($total / $limit),
            'q'         => $q,
            'notebooks' => $this->notebooks->findAll(),
            'tags'      => $this->tags->findAll(),
        ]);
    }
}
