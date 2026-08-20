<?php

declare(strict_types=1);

namespace SQNote\Web\Controller;

use SQNote\Repository\NoteHistoryRepository;
use SQNote\Repository\NoteRepository;
use SQNote\Repository\NotebookRepository;
use SQNote\Repository\TagRepository;

class NoteHistoryController extends AbstractController
{
    public function __construct(
        \Twig\Environment $twig,
        private NoteRepository $notes,
        private NoteHistoryRepository $noteHistory,
        private NotebookRepository $notebooks,
        private TagRepository $tags,
    ) {
        parent::__construct($twig);
    }

    public function index(array $params): never
    {
        $note = $this->notes->findById($params['id']);
        if (!$note) {
            http_response_code(404);
            echo '<h1>404 Not Found</h1>';
            exit;
        }

        $history = $this->noteHistory->findByNoteId($params['id']);

        $this->render('note/history.html.twig', [
            'note'      => $note,
            'history'   => $history,
            'notebooks' => $this->notebooks->findAll(),
            'tags'      => $this->tags->findAll(),
        ]);
    }
}
