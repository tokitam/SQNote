<?php

declare(strict_types=1);

namespace SQNote\Web\Controller;

use SQNote\Repository\NoteRepository;
use SQNote\Repository\NotebookRepository;
use SQNote\Repository\TagRepository;
use SQNote\Service\NoteService;

class NoteEditController extends AbstractController
{
    public function __construct(
        \Twig\Environment $twig,
        private NoteRepository $notes,
        private NotebookRepository $notebooks,
        private TagRepository $tags,
        private NoteService $noteService,
    ) {
        parent::__construct($twig);
    }

    public function create(): never
    {
        $this->render('note/edit.html.twig', [
            'note'      => null,
            'notebooks' => $this->notebooks->findAll(),
            'tags'      => $this->tags->findAll(),
        ]);
    }

    public function edit(array $params): never
    {
        $note = $this->notes->findById($params['id']);
        if (!$note) {
            http_response_code(404);
            echo '<h1>404 Not Found</h1>';
            exit;
        }
        $this->render('note/edit.html.twig', [
            'note'      => $note,
            'notebooks' => $this->notebooks->findAll(),
            'tags'      => $this->tags->findAll(),
        ]);
    }

    public function store(): never
    {
        // JS 非対応環境向けのフォームサブミット処理
        $title       = $_POST['title'] ?? '';
        $content     = $_POST['content'] ?? '';
        $contentType = $_POST['content_type'] ?? 'markdown';
        $notebookId  = $_POST['notebook_id'] ?: null;
        $tags        = array_filter(array_map('trim', explode(',', $_POST['tags'] ?? '')));

        $id = $this->noteService->create([
            'title'        => $title,
            'content'      => $content,
            'content_type' => $contentType,
            'notebook_id'  => $notebookId,
            'tags'         => $tags,
        ]);
        $this->redirect('/notes/' . $id);
    }

    public function update(array $params): never
    {
        $note = $this->notes->findById($params['id']);
        if (!$note) {
            http_response_code(404);
            echo '<h1>404 Not Found</h1>';
            exit;
        }

        $this->noteService->update($params['id'], [
            'title'       => $_POST['title'] ?? $note['title'],
            'content'     => $_POST['content'] ?? $note['content'],
            'content_type' => $_POST['content_type'] ?? $note['content_type'],
            'notebook_id' => $_POST['notebook_id'] ?: null,
            'tags'        => array_filter(array_map('trim', explode(',', $_POST['tags'] ?? ''))),
        ]);
        $this->redirect('/notes/' . $params['id']);
    }
}
